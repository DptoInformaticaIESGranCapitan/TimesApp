<?php
App::uses('AppController', 'Controller');
/**
 * Taxes Controller
 *
 * @property Tax $Tax
 * @property PaginatorComponent $Paginator
 * @property SessionComponent $Session
 */
class TaxesController extends AppController {

/**
 * Components
 *
 * @var array
 */
	public $components = array('Paginator', 'Session');

	public $helpers = array('Fn5');

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
		$this->Paginator->settings = array(
	        'limit' => 10
    	);

		$this->Tax->recursive = 0;
		$this->set('taxes', $this->Paginator->paginate());

		// Settings
		$appSettings = include APP_SETTINGS;
		$this->set('appSettings', $appSettings);
	}

/**
 * add method
 *
 * @return void
 */
	public function add() {
		if ($this->request->is('post')) {
			$this->Tax->create();
			if ($this->Tax->save($this->request->data)) {
				$this->Session->setFlash(__('The tax has been saved.'), 'flash_success');
				return $this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The tax could not be saved. Please, try again.'), 'flash_danger');
			}
		}
	}

/**
 * edit method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function edit($id = null) {
		if (!$this->Tax->exists($id)) {
			throw new NotFoundException(__('Invalid tax'));
		}
		if ($this->request->is(array('post', 'put'))) {
			if ($this->Tax->save($this->request->data)) {
				$this->Session->setFlash(__('The tax has been saved.'), 'flash_success');
				return $this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The tax could not be saved. Please, try again.'), 'flash_danger');
			}
		} else {
			$options = array('conditions' => array('Tax.' . $this->Tax->primaryKey => $id));
			$this->request->data = $this->Tax->find('first', $options);
		}
	}

/**
 * delete method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function delete($id = null) {
		$this->Tax->id = $id;
		if (!$this->Tax->exists()) {
			throw new NotFoundException(__('Invalid tax'));
		}
		$this->request->onlyAllow('post', 'delete');
		if ($this->Tax->delete()) {
			$this->Session->setFlash(__('The tax has been deleted.'), 'flash_success');
		} else {
			$this->Session->setFlash(__('The tax could not be deleted. Please, try again.'), 'flash_danger');
		}
		return $this->redirect(array('action' => 'index'));
	}}
