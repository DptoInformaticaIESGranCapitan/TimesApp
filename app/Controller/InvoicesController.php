<?php
App::uses('AppController', 'Controller');
App::uses('CakeEmail', 'Network/Email');

/**
 * Invoices Controller
 *
 * @property Invoice $Invoice
 * @property PaginatorComponent $Paginator
 * @property SessionComponent $Session
 */
class InvoicesController extends AppController {

/**
 * Components
 *
 * @var array
 */
	public $components = array('Paginator', 'Session');

	private $seed = 1990;

/**
 * beforeFilter method
 * @return void
 */
	public function beforeFilter() {
		parent::beforeFilter();
		$this->Auth->allow('view', 'generatePDF');
	}

/**
 * isAuthorized method
 * @param  array  $user user currently log in
 * @return boolean      
 */
	public function isAuthorized($user = null) {
        if($user['role'] != 'overlord'){
        	return false;
        }
        return true;
    }

/**
 * index method
 *
 * @return void
 */
	public function index() {
		$this->Invoice->recursive = 0;
		$this->set('invoices', $this->Paginator->paginate());

		// Settings
		$appSettings = include APP_SETTINGS;
		$this->set('appSettings', $appSettings);

		$this->set('seed', $this->seed);
	}

/**
 * view method
 * @return void       
 */
	public function view($id = null) {
		$this->layout="invoice_permalink";

		$id = $id / $this->seed;
		
		if (!$this->Invoice->exists($id)) {
			throw new NotFoundException(__('Invalid invoice'));
		}

		// Settings
		$appSettings = include APP_SETTINGS;
		$this->set('appSettings', $appSettings);

		// data
		$options = array('conditions' => array('Invoice.id' => $id));
		$invoice = $this->Invoice->find('first', $options);
		$this->set('invoice', $invoice);

		$this->loadModel('Tax');
		$taxes = $this->Tax->find('all');
		$this->set('taxes', $taxes);

		//dates
		$this->set('dueDays', $this->countDaysBetween($invoice['Invoice']['invoice_date'], $invoice['Invoice']['due_date']));
	}

/**
 * add method
 *
 * @return void
 */
	public function add($fromProject=null) {
		if ($this->request->is('post')) {
			$this->Invoice->create();
			if ($this->Invoice->saveAll($this->request->data)) {
				$this->Session->setFlash(__('The invoice has been saved.'), 'flash_success');
				return $this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The invoice could not be saved. Please, try again.'), 'flash_danger');
			}
		}
		// From project
		if($fromProject != null){
			if (!$this->Invoice->Project->exists($fromProject)) {
				throw new NotFoundException(__('Invalid project for invoicing'));
			}
			$options = array('conditions' => array('Project.' . $this->Invoice->Project->primaryKey => $fromProject));

			$theProject = $this->Invoice->Project->find('first', $options);
			$this->set('fromProject', $theProject);
			// Calcula las horas y servicios
			$this->set('hoursServices', $this->getServicesFromHours($fromProject));
		}
		// Last invoice number
		$lastUsed = 0;
		$lastInvoice = $this->Invoice->find('all', array(
			'fields' => array(
				'Invoice.title'
				),
			'order' => array(
				'Invoice.created' => 'desc'
				),
			'limit' => 1
			));
		if($lastInvoice){
			$lastUsed = $lastInvoice[0]['Invoice']['title'];
		}
		$this->set('lastUsed', $lastUsed);
		// Next number
		$nextToUse = $this->number_pad(($lastUsed + 1), 7);
		$this->set('nextToUse', $nextToUse);


		// Normal
		$options = array('conditions' => array('status' => 1));
		$projects = $this->Invoice->Project->find('list');
		$clients = $this->Invoice->Client->find('list', $options);
		$this->loadModel('Tax');
		$this->loadModel('Service');
		$this->loadModel('Product');
		$taxes = $this->Tax->find('all', $options);
		$services = $this->Service->find('list', $options);
		$products = $this->Product->find('list', $options);
		$this->set(compact('projects','services', 'products', 'clients'));
		$this->set('taxes', $taxes);
		
		// Settings
		$appSettings = include APP_SETTINGS;
		$this->set('appSettings', $appSettings);
	}

/**
 * edit method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function edit($id = null) {
		if (!$this->Invoice->exists($id)) {
			throw new NotFoundException(__('Invalid invoice'));
		}
		if ($this->request->is(array('post', 'put'))) {
			if ($this->Invoice->save($this->request->data)) {
				$this->Session->setFlash(__('The invoice has been saved.'), 'flash_success');
				return $this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The invoice could not be saved. Please, try again.'), 'flash_danger');
			}
		} else {
			$options = array('conditions' => array('Invoice.' . $this->Invoice->primaryKey => $id));
			$this->request->data = $this->Invoice->find('first', $options);
		}

		$options = array('conditions' => array('status' => 1));
		$projects = $this->Invoice->Project->find('list');
		$clients = $this->Invoice->Client->find('list', $options);
		$this->loadModel('Tax');
		$taxes = $this->Tax->find('all', $options);
		$this->set(compact('projects', 'clients'));
		$this->set('taxes', $taxes);
	}

/**
 * delete method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function delete($id = null) {
		$this->Invoice->id = $id;
		if (!$this->Invoice->exists()) {
			throw new NotFoundException(__('Invalid invoice'));
		}
		$this->request->onlyAllow('post', 'delete');
		if ($this->Invoice->delete()) {
			$this->Session->setFlash(__('The invoice has been deleted.'), 'flash_success');
		} else {
			$this->Session->setFlash(__('The invoice could not be deleted. Please, try again.'), 'flash_danger');
		}
		return $this->redirect(array('action' => 'index'));
	}

/**
 * getServicesFromHours private method
 * @param  [type] $id [description]
 * @return [type]     [description]
 */
	private function getServicesFromHours($id=null){
		/*SELECT service_id, SUM(hours) FROM `hours` WHERE project_id = 4 AND billed = true GROUP BY service_id;*/
		$this->loadModel('Hour');
		$options = array(
			'conditions' => array('project_id' => $id, 'billed' => true),
			'fields' => array('service_id', 'SUM(hours) as hours'),
			'group' => array('service_id')
			);
		$hours = $this->Hour->find('all', $options);

		if(!empty($hours)){
			$this->loadModel('Service');
			$result = array();
			foreach ($hours as $key => $value) {
				$serviceOptions = array(
					'conditions' => array(
						'Service.id' => $value['Hour']['service_id'],
						)
					);
				$result[$key] = $this->Service->find('first', $serviceOptions);
				array_push($result[$key], $value[0]['hours']);
			}
		}else{
			$result = null;
		}
		return $result;
	}

/**
 * getLine method for Ajax request
 * @param  number $type type of line
 * @param  number $id   id of line
 * @return void       
 */
	public function getLine($type=null, $id=null, $index=0){
		$this->layout="ajax";

		switch ($type) {
			case 1: // service
				$this->loadModel('Service');
				if (!$this->Service->exists($id)) {
					throw new NotFoundException(__('Invalid service'));
				}
				$options = array('conditions' => array('Service.' . $this->Service->primaryKey => $id));
				$this->set('index', $index);
				$this->set('service', $this->Service->find('first', $options));
				break;
			case 2: // product
				$this->loadModel('Product');
				if (!$this->Product->exists($id)) {
					throw new NotFoundException(__('Invalid product'));
				}
				$options = array('conditions' => array('Product.' . $this->Product->primaryKey => $id));
				$this->set('index', $index);
				$this->set('product', $this->Product->find('first', $options));
				break;
			
			default:

				break;
		}

		$this->loadModel('Tax');
		$options = array('conditions' => array('status' => 1));
		$taxes = $this->Tax->find('all', $options);
		$this->set('taxes', $taxes);
	}

/**
 * getProjectsByClient method for Ajax request
 * @return void       
 */
	public function getProjectsByClient(){
		$this->layout="ajax";
		if(isset($_POST['clientIdJS'])) {
			$clientIdJS = $_POST['clientIdJS'];
		}

		$options = array('conditions' => array('client_id' => $clientIdJS, 'billable' => 1));
		$projectsByClient = $this->Invoice->Project->find('all', $options);
		$this->set('projects', $projectsByClient);
	}
/**
 * getMessage ajax method
 * {{clientName}}
 * {{amount}}
 * {{permalink}}
 * 
 * @param  string $message [description]
 * @return string          [description]
 */
	function getMessage($id = null){
		$this->layout = 'ajax';

		$id = $id / $this->seed;

		if (!$this->Invoice->exists($id)) {
			throw new NotFoundException(__('Invalid invoice'));
		}
		// Invoice
		$options = array('conditions' => array(
			'Invoice.id' => $id
			),
		);
		$invoice = $this->Invoice->find('first', $options);

		// Settings
		$appSettings = include APP_SETTINGS;

		$message = "";
		if(isset($appSettings['email_message'])){
			$message = $appSettings['email_message'];
		}
		$link = Router::url(array('controller' => 'invoice', 'action' => 'view', $id * $this->seed), array('full' => true));
		$message = str_replace("{{clientName}}", $invoice['Client']['name'], $message);
		$message = str_replace("{{amount}}", number_format($invoice['Invoice']['amount'], 2), $message);
		$message = str_replace("{{currencySymbol}}", $invoice['Invoice']['currency_symbol'], $message);
		$message = str_replace("{{currencyCode}}", $invoice['Invoice']['currency_code'], $message);
		$message = str_replace("{{permalink}}", $link, $message);
		$message = str_replace("{{companyName}}", $appSettings['companyName'], $message);

		$this->set('response', $message);
	}	

/**
 * sendInvoice method for sending invoice to client
 * @return void       
 */
	public function sendInvoice(){
		if(isset($_POST['subject']) && isset($_POST['message']) && isset($_POST['receiver'])) {
			$Email = new CakeEmail('smtp');

			$Email->to($_POST['receiver'])->subject($_POST['subject']);

			try {
			    $Email->send();
			    // update status invoice to sent
	        	if (!$this->Invoice->exists($_POST['invoice_id'])) {
					throw new NotFoundException(__('Invalid invoice'));
				}
				$this->Invoice->id = $_POST['invoice_id'];

				$invoice = $this->Invoice->find('first', array('conditions' => array('Invoice.id' => $this->Invoice->id)));

				if($invoice['Invoice']['status'] < 2){
					$this->Invoice->saveField('status', 1);
				}

		        $this->Session->setFlash(__('Mail sent.'), 'flash_success');
		        return $this->redirect(array('controller'=>'Invoices','action'=>'index'));

			} catch (SocketException $e) { // Exception would be too generic, so use SocketException here
			    $errorMessage = $e->getMessage();
			    $this->Session->setFlash(__('The email couldn\'t be sent. Check your Email settings.'), 'flash_danger');
		    	return $this->redirect(array('controller'=>'Invoices','action'=>'index'));
			}
			
		}
	}


/**
 * generatePDF method for generating invoice's PDF
 * @return void       
 */
	public function generatePDF($id=null){

		if ($id != null) {

			$this->layout = '/pdf/default';

			// Settings
			$appSettings = include APP_SETTINGS;
			$this->set('appSettings', $appSettings);

			// data
			$options = array('conditions' => array('Invoice.id' => $id));
			$invoice = $this->Invoice->find('first', $options);
			$this->set('invoice', $invoice);

			$this->loadModel('Tax');
			$taxes = $this->Tax->find('all');
			$this->set('taxes', $taxes);

			$this->render('/Pdf/invoice');

		}else{
			throw new BadRequestException(__('Error generating PDF'));

		}
	}

/**
 * countDaysBetween method
 * @param  date $start start date
 * @param  date $end   end date
 * @return int        days between
 */
	private function countDaysBetween($start, $end){
		$startTimeStamp = strtotime($start);
		$endTimeStamp = strtotime($end);
		$timeDiff = abs($endTimeStamp - $startTimeStamp);
		$numberDays = $timeDiff/86400;  // 86400 seconds in one day
		return intval($numberDays);
	}

/**
 * number_pad method
 * @param  int $number [description]
 * @param  int $n      [description]
 * @return String         [description]
 */
	private function number_pad($number,$n) {
    	return str_pad((int) $number,$n,"0",STR_PAD_LEFT);
	} 
}
