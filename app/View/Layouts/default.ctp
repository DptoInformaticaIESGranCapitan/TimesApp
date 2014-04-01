<?php $appName = 'TimesApp' ?>
<!DOCTYPE html>
<html class="no-js" lang="en">
<head>
	<?php echo $this->Html->charset(); ?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>
		<?php echo $appName ?>:
		<?php echo $title_for_layout; ?>
	</title>
	<?php
		echo $this->Html->meta('icon');

		echo $this->Html->css('app');

		echo $this->fetch('meta');
		echo $this->fetch('css');
		echo $this->fetch('script');
		echo $this->Html->script('modernizr');
	?>
</head>
<body>
	<!-- Header -->
	<header class="top-bar-wrapper">
	    <div class="row">
		    <div class="large-12 columns">
		    	<?php echo $this->element('navbar'); ?>
		   	</div>
		</div>
	</header>


	<!-- Content -->
	<div class="row">
    	<div class="large-12 columns">
    	<div class="page-wrapper">
		<?php echo $this->Session->flash(); ?>
		<?php echo $this->fetch('content'); ?>
		</div>
		</div>
	</div>

	<!-- Footer -->
    <footer id="body-footer">
      <div class="row">
        <div class="large-12 medium-12 columns">
        	<p>&copy; 2014 NoTime. All rights reserved. Nop, es coña XD</p>
        </div>
      </div>
    </footer>
    <!-- Scripts -->
    <?php echo $this->Html->script('jquery.min'); ?>
    <?php echo $this->Html->script('foundation.min'); ?>
</body>
</html>