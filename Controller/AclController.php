<?php
/**
 * Acl Manager
 *
 * A CakePHP Plugin to manage Acl
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author        Frédéric Massart - FMCorz.net
 * @copyright     Copyright 2011, Frédéric Massart
 * @link          http://github.com/FMCorz/AclManager
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
 
class AclController extends AclManagerAppController {

	var $paginate = array();
	protected $_authorizer = null;

	/**
	 * beforeFitler
	 */
	public function beforeFilter() {
		parent::beforeFilter();
		
		/**
		 * Loading required Model
		 */
		$aros = Configure::read('AclManager.models');
		foreach ($aros as $aro) {
			$this->loadModel($aro);
		}
		
		/**
		 * Pagination
		 */
		$aros = Configure::read('AclManager.aros');
		foreach ($aros as $aro) {
			$limit = Configure::read("AclManager.{$aro}.limit");
			$limit = empty($limit) ? 4 : $limit;
			$this->paginate[$this->{$aro}->alias] = array(
				'recursive' => -1,
				'limit' => $limit
			);
		}
	}

	/**
	 * Delete everything
	 */
	public function drop() {
		$this->Acl->Aco->deleteAll(array("1 = 1"));
		$this->Acl->Aro->deleteAll(array("1 = 1"));
		$this->Session->setFlash(__("Both ACOs and AROs have been dropped"));
		$this->redirect(array("action" => "index"));
	}
	
	/**
	 * Delete all permissions
	 */
	public function drop_perms() {
		if ($this->Acl->Aro->Permission->deleteAll(array("1 = 1"))) {
			$this->Session->setFlash(__("Permissions dropped"));
		} else {
			$this->Session->setFlash(__("Error while trying to drop permissions"));
		}
		$this->redirect(array("action" => "index"));
	}

	/**
	 * Index action
	 */
	public function index() {
	}

	/**
	 * Manage Permissions
	 */
	public function permissions() {

		// Saving permissions
		if ($this->request->is('post') || $this->request->is('put')) {
			$perms =  isset($this->request->data['Perms']) ? $this->request->data['Perms'] : array();
			foreach ($perms as $aco => $aros) {
				$action = str_replace(":", "/", $aco);
				foreach ($aros as $node => $perm) {
					list($model, $id) = explode(':', $node);
					$node = array('model' => $model, 'foreign_key' => $id);
					if ($perm) { 
						$this->Acl->allow($node, $action);
					}
					else {
						$this->Acl->deny($node, $action);
					}
				}
			} 
		}
		
		$model = isset($this->request->params['named']['aro']) ? $this->request->params['named']['aro'] : null;
		if (!$model || !in_array($model, Configure::read('AclManager.aros'))) {
			$model = Configure::read('AclManager.aros');
			$model = $model[0];
		}

		$Aro = $this->{$model};
		$aros = $this->paginate($Aro->alias);
		
		/**
		 * Build permissions info
		 */
		$acos = $this->Acl->Aco->find('all', array('order' => 'Aco.lft ASC', 'recursive' => 0));
		$perms = array();
		$parents = array();
		foreach ($acos as $key => $data) {
			$aco =& $acos[$key];
			$aco = array('Aco' => $data['Aco'], 'Action' => array());
			$id = $aco['Aco']['id'];
			
			// Generate path
			if ($aco['Aco']['parent_id'] && isset($parents[$aco['Aco']['parent_id']])) {
				$parents[$id] = $parents[$aco['Aco']['parent_id']] . '/' . $aco['Aco']['alias'];
			} else {
				$parents[$id] = $aco['Aco']['alias'];
			}
			$aco['Action'] = $parents[$id];

			// Fetching permissions per ARO
			$acoNode = $aco['Action'];
			foreach($aros as $aro) {
				$aroNode = array('model' => $Aro->alias, 'foreign_key' => $aro[$Aro->alias]['id']);
				$perms[str_replace('/', ':', $acoNode)][$Aro->alias . ":" . $aro[$Aro->alias]['id']] = $this->Acl->check($aroNode, $acoNode);
			}
		}
		
		$this->request->data = array('Perms' => $perms);
		$this->set('aroAlias', $Aro->alias);
		$this->set('aroDisplayField', $Aro->displayField);
		$this->set(compact('acos', 'aros'));
	}

	/**
	 * Update ACOs
	 * Sets the missing actions in the database
	 */
	public function update_acos() {
		
		$count = 0;
		
		// Root node
		$aco = $this->_action(array(), '');
		if (!$rootNode = $this->Acl->Aco->node($aco)) {
			$rootNode = $this->_buildAcoNode($aco, null);
			$count++;
		}
		
		// Loop around each controller and its actions
		$allActions = $this->_getActions();
		foreach ($allActions as $controller => $actions) {
			if (empty($actions)) {
				continue;
			}
			
			$parentNode = $rootNode;
			list($plugin, $controller) = pluginSplit($controller);
			
			// Plugin
			$aco = $this->_action(array('plugin' => $plugin), '/:plugin/');
			$newNode = $parentNode;
			if ($plugin && !$newNode = $this->Acl->Aco->node($aco)) {
				$newNode = $this->_buildAcoNode($plugin, $parentNode);
				$count++;
			}
			$parentNode = $newNode;
			
			// Controller
			$aco = $this->_action(array('controller' => $controller, 'plugin' => $plugin), '/:plugin/:controller');
			if (!$newNode = $this->Acl->Aco->node($aco)) {
				$newNode = $this->_buildAcoNode($controller, $parentNode);
				$count++;
			}
			$parentNode = $newNode;

			// Actions
			foreach ($actions as $action) {
				$aco = $this->_action(array(
					'controller' => $controller,
					'action' => $action,
					'plugin' => $plugin
				));
				if (!$node = $this->Acl->Aco->node($aco)) {
					$this->_buildAcoNode($action, $parentNode);
					$count++;
				}
			}
		}
		
		$this->Session->setFlash(sprintf(__("%d ACOs have been created/updated"), $count));
		$this->redirect($this->request->referer());
	}

	/**
	 * Update AROs
	 * Sets the missing AROs in the database
	 */
	public function update_aros() {
	
		// Debug off to enable redirect
		Configure::write('debug', 0);
		
		$count = 0;
		$type = 'Aro';
			
		// Over each ARO Model
		$objects = Configure::read("AclManager.aros");
		foreach ($objects as $object) {
			
			$Model = $this->{$object};

			$items = $Model->find('all');
			foreach ($items as $item) {
	
				$item = $item[$Model->alias];
				$Model->id = $item['id'];
				$node = $Model->node();
				
				// Node exists
				if ($node) {
					$parent = $Model->parentNode();
					if (!empty($parent)) {
						$parent = $Model->node($parent, $type);
					}
					$parent = isset($parent[0][$type]['id']) ? $parent[0][$type]['id'] : null;
					
					// Parent is incorrect
					if ($parent != $node[0][$type]['parent_id']) {
						$node = null;
					}
				}
				
				// Missing Node or incorrect
				if (empty($node)) {
					
					// Extracted from AclBehavior::afterSave (and adapted)
					$parent = $Model->parentNode();
					if (!empty($parent)) {
						$parent = $Model->node($parent, $type);
					}
					$data = array(
						'parent_id' => isset($parent[0][$type]['id']) ? $parent[0][$type]['id'] : null,
						'model' => $Model->name,
						'foreign_key' => $Model->id
					);
					
					// Creating ARO
					$this->Acl->{$type}->create($data);
					$this->Acl->{$type}->save();
					$count++;
				}
			}
		}
		
		$this->Session->setFlash(sprintf(__("%d AROs have been created"), $count));
		$this->redirect($this->request->referer());
	}

	/**
	 * Gets the action from Authorizer
	 */
	protected function _action($request = array(), $path = '/:plugin/:controller/:action') {
		$plugin = empty($request['plugin']) ? null : Inflector::camelize($request['plugin']) . '/';
		$request = array_merge(array('controller' => null, 'action' => null, 'plugin' => null), $request);
		$authorizer = $this->_getAuthorizer();
		return $authorizer->action($request, $path);
	}

	/**
	 * Build ACO node
	 *
	 * @return node
	 */
	protected function _buildAcoNode($alias, $parent_id = null) {
		if (is_array($parent_id)) {
			$parent_id = $parent_id[0]['Aco']['id'];
		}
		$this->Acl->Aco->create(array('alias' => $alias, 'parent_id' => $parent_id));
		$this->Acl->Aco->save();
		return array(array('Aco' => array('id' => $this->Acl->Aco->id)));
	}

	/**
	 * Returns all the Actions found in the Controllers
	 * 
	 * Ignores:
	 * - protected and private methods (starting with _)
	 * - Controller methods
	 * - methods matching Configure::read('AclManager.ignoreActions')
	 * 
	 * @return array('Controller' => array('action1', 'action2', ... ))
	 */
	protected function _getActions() {
		$ignore = Configure::read('AclManager.ignoreActions');
		$methods = get_class_methods('Controller');
		foreach($methods as $method) {
			$ignore[] = $method;
		}
		
		$controllers = $this->_getControllers();
		$actions = array();
		foreach ($controllers as $controller) {
		    
		    list($plugin, $name) = pluginSplit($controller);
			
		    $methods = get_class_methods($name . "Controller");
			$methods = array_diff($methods, $ignore);
			foreach ($methods as $key => $method) {
				if (strpos($method, "_") === 0 || in_array($controller . '/' . $method, $ignore)) {
					unset($methods[$key]);
				}
			}
			$actions[$controller] = $methods;
		}
		
		return $actions;
	}

	/**
	 * Gets the Authorizer object from Auth
	 */
	protected function _getAuthorizer() {
		if (!is_null($this->_authorizer)) {
			return $this->_authorizer;
		}
		$authorzeObjects = $this->Auth->_authorizeObjects;
		foreach ($authorzeObjects as $object) {
			if (!$object instanceOf ActionsAuthorize) {
				continue;
			}
			$this->_authorizer = $object; 
			break;
		}
		if (empty($this->_authorizer)) {
			$this->Session->setFlash(__("ActionAuthorizer could not be found"));
			$this->redirect($this->referer());
		}
		return $this->_authorizer;
	}

	/**
	 * Returns all the controllers from Cake and Plugins
	 * Will only browse loaded plugins
	 *
	 * @return array('Controller1', 'Plugin.Controller2')
	 */
	protected function _getControllers() {
		
		// Getting Cake controllers
		$objects = array('Cake' => array());
		$objects['Cake'] = App::objects('Controller');
		$unsetIndex = array_search("AppController", $objects['Cake']);
		if ($unsetIndex !== false) {
			unset($objects['Cake'][$unsetIndex]);
		}
		
		// App::objects does not return PagesController
		if (!in_array('PagesController', $objects['Cake'])) {
		    array_unshift($objects['Cake'], 'PagesController');
		}
		
		// Getting Plugins controllers
		$plugins = CakePlugin::loaded();
		foreach ($plugins as $plugin) {
			$objects[$plugin] = App::objects($plugin . '.Controller');
			$unsetIndex = array_search($plugin . "AppController", $objects[$plugin]);
			if ($unsetIndex !== false) {
				unset($objects[$plugin][$unsetIndex]);
			}
		}

		// Around each controller
		$return = array();
		foreach ($objects as $plugin => $controllers) {
			$controllers = str_replace("Controller", "", $controllers);
			foreach ($controllers as $controller) {
				if ($plugin !== "Cake") {
					$controller = $plugin . "." . $controller;
				}
				if (App::import('Controller', $controller)) {
					$return[] = $controller;
				}
			}
		}

		return $return;
	}
}