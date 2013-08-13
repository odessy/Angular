<?php

App::uses('FormHelper', 'View/Helper');

class BsAnFormHelper extends FormHelper {

  	public $_options = array(    
					'inputDefaults' => array(
						'format' => array('before', 'label', 'between', 'input', 'error', 'after'),
						'div' => array('class' => 'control-group'),
						'label' => array('class' => 'control-label'),
						'between' => '<div class="controls">',
						'after' => '</div>',
						'error' => array('attributes' => array('wrap' => 'div', 'class' => 'error-message') )
					)
				);
						
	public $_rules = array();
	
	public $_scripts = array();
	
	public $_allowedFields = array('isunique');

	public function __construct(View $View, $settings = array()) {
		parent::__construct($View, $settings);
		$this->_inputDefaults = $this->_options['inputDefaults'];
	}
	
	
	public function create($model = null, $options = array()) {
		$created = $id = false;
		$append = '';

		if (is_array($model) && empty($options)) {
			$options = $model;
			$model = null;
		}

		if (empty($model) && $model !== false && !empty($this->request->params['models'])) {
			$model = key($this->request->params['models']);
		} elseif (empty($model) && empty($this->request->params['models'])) {
			$model = false;
		}
		$this->defaultModel = $model;
		
		if(!isset($options['ng-app']))
		{
			$options['ng-app'] = $model;
		}

		$key = null;
		if ($model !== false) {
			list($plugin, $model) = pluginSplit($model, true);
			$key = $this->_introspectModel($plugin . $model, 'key');
			$this->setEntity($model, true);
		}

		if ($model !== false && $key) {
			$recordExists = (
				isset($this->request->data[$model]) &&
				!empty($this->request->data[$model][$key]) &&
				!is_array($this->request->data[$model][$key])
			);

			if ($recordExists) {
				$created = true;
				$id = $this->request->data[$model][$key];
			}
		}

		$options = array_merge(array(
			'type' => ($created && empty($options['action'])) ? 'put' : 'post',
			'action' => null,
			'url' => null,
			'class' => 'row form-horizontal',
			'default' => true,
			'encoding' => strtolower(Configure::read('App.encoding')),
			'inputDefaults' => array()),
		$options);
		$this->inputDefaults($options['inputDefaults'], true); //added true
		unset($options['inputDefaults']);

		if (!isset($options['id'])) {
			$domId = isset($options['action']) ? $options['action'] : $this->request['action'];
			$options['id'] = $this->domId($domId . 'Form');
		}

		if ($options['action'] === null && $options['url'] === null) {
			$options['action'] = $this->request->here(false);
		} elseif (empty($options['url']) || is_array($options['url'])) {
			if (empty($options['url']['controller'])) {
				if (!empty($model)) {
					$options['url']['controller'] = Inflector::underscore(Inflector::pluralize($model));
				} elseif (!empty($this->request->params['controller'])) {
					$options['url']['controller'] = Inflector::underscore($this->request->params['controller']);
				}
			}
			if (empty($options['action'])) {
				$options['action'] = $this->request->params['action'];
			}

			$plugin = null;
			if ($this->plugin) {
				$plugin = Inflector::underscore($this->plugin);
			}
			$actionDefaults = array(
				'plugin' => $plugin,
				'controller' => $this->_View->viewPath,
				'action' => $options['action'],
			);
			$options['action'] = array_merge($actionDefaults, (array)$options['url']);
			if (empty($options['action'][0]) && !empty($id)) {
				$options['action'][0] = $id;
			}
		} elseif (is_string($options['url'])) {
			$options['action'] = $options['url'];
		}
		unset($options['url']);

		switch (strtolower($options['type'])) {
			case 'get':
				$htmlAttributes['method'] = 'get';
			break;
			case 'file':
				$htmlAttributes['enctype'] = 'multipart/form-data';
				$options['type'] = ($created) ? 'put' : 'post';
			case 'post':
			case 'put':
			case 'delete':
				$append .= $this->hidden('_method', array(
					'name' => '_method', 'value' => strtoupper($options['type']), 'id' => null,
					'secure' => self::SECURE_SKIP
				));
			default:
				$htmlAttributes['method'] = 'post';
			break;
		}
		$this->requestType = strtolower($options['type']);

		$action = $this->url($options['action']);
		unset($options['type'], $options['action']);

		if (!$options['default']) {
			if (!isset($options['onsubmit'])) {
				$options['onsubmit'] = '';
			}
			$htmlAttributes['onsubmit'] = $options['onsubmit'] . 'event.returnValue = false; return false;';
		}
		unset($options['default']);

		if (!empty($options['encoding'])) {
			$htmlAttributes['accept-charset'] = $options['encoding'];
			unset($options['encoding']);
		}

		$htmlAttributes = array_merge($options, $htmlAttributes);

		$this->fields = array();
		if ($this->requestType !== 'get') {
			$append .= $this->_csrfField();
		}

		if (!empty($append)) {
			$append = $this->Html->useTag('hiddenblock', $append);
		}

		if ($model !== false) {
			$this->setEntity($model, true);
			$this->_introspectModel($model, 'fields');
		}
		return $this->compressStyle($this->getCss()).$this->Html->useTag('form', $action, $htmlAttributes) . $append;
		//return $this->getCss().$this->Html->useTag('form', $action, $htmlAttributes) . $append;
	}

	
/**
 * Generate input
 */	
 
	public function input($fieldName, $options = array()) {
		$this->setEntity($fieldName);
		$options = $this->_parseOptions($options, $fieldName);

		$divOptions = $this->_divOptions($options, $fieldName);
		unset($options['div']);

		if ($options['type'] === 'radio' && isset($options['options'])) {
			$radioOptions = (array)$options['options'];
			unset($options['options']);
		}

		$label = $this->_getLabel($fieldName, $options);
		if ($options['type'] !== 'radio') {
			unset($options['label']);
		}

		$error = $this->_extractOption('error', $options, null);
		unset($options['error']);

		$errorMessage = $this->_extractOption('errorMessage', $options, true);
		unset($options['errorMessage']);

		$selected = $this->_extractOption('selected', $options, null);
		unset($options['selected']);

		if ($options['type'] === 'datetime' || $options['type'] === 'date' || $options['type'] === 'time') {
			$dateFormat = $this->_extractOption('dateFormat', $options, 'MDY');
			$timeFormat = $this->_extractOption('timeFormat', $options, 12);
			unset($options['dateFormat'], $options['timeFormat']);
		}

		$type = $options['type'];
		$out = array('before' => $options['before'], 'label' => $label, 'between' => $options['between'], 'after' => $options['after']);
		$format = $this->_getFormat($options);

		unset($options['type'], $options['before'], $options['between'], $options['after'], $options['format']);

		$out['error'] = null;
		if ($type !== 'hidden' && $error !== false) {
			$errMsg = $this->error($fieldName, $error);
			if ($errMsg) {
				$divOptions = $this->addClass($divOptions, 'error');
				if ($errorMessage) {
					$out['error'] = $errMsg;
				}
			}
		}

		if ($type === 'radio' && isset($out['between'])) {
			$options['between'] = $out['between'];
			$out['between'] = null;
		}
		$out['input'] = $this->_getInput(compact('type', 'fieldName', 'options', 'radioOptions', 'selected', 'dateFormat', 'timeFormat'));

		$output = '';
		foreach ($format as $element) {
			$output .= $out[$element];
		}

		if (!empty($divOptions['tag'])) {
			$tag = $divOptions['tag'];
			unset($divOptions['tag']);
			$output = $this->Html->tag($tag, $output, $divOptions);
		}
		return $output;
	}

/**
 * Generates input options array
 *
 * @param type $options
 * @return array Options
 */
	protected function _parseOptions($options, $fieldName = null) {
		//get filed name
		$fieldName = $this->sanitizeFieldName($fieldName);
		$model = $this->model();
		$object = $this->_getModel($model);//;$this->defaultModel);
		
		//debug($fieldName);
		
		$options = array_merge(
			array('before' => null, 'between' => null, 'after' => null, 'format' => null),
			$this->_inputDefaults,
			$options
		);

		if (!isset($options['type'])) {
			$options = $this->_magicOptions($options);
		}
		
		if(!isset($options['ng-model'])){
			if($fieldName != null){
				$options['ng-model'] = $this->correctName($fieldName).'check';
				$options[$this->correctName($fieldName).'validate'] = '';
			}
			$options['class'] = 'immediate-help';
		}
		
		if(isset($this->request->data[$model][$fieldName])) {
			$options['ng-init'] = $this->correctName($fieldName).'check="'.$this->request->data[$model][$fieldName].'"';
		}
		
		$options['after'] = $this->getValidationRules($fieldName, $object);

		if (in_array($options['type'], array('checkbox', 'radio', 'select'))) {
			$options = $this->_optionsOptions($options);
		}

		if (isset($options['rows']) || isset($options['cols'])) {
			$options['type'] = 'textarea';
		}

		if ($options['type'] === 'datetime' || $options['type'] === 'date' || $options['type'] === 'time' || $options['type'] === 'select') {
			$options += array('empty' => false);
		}
		return $options;
	}
	
/**
 * Generate div options for input
 *
 * @param array $options
 * @return array
 */
	protected function _divOptions($options, $fieldName = null) {
		if ($options['type'] === 'hidden') {
			return array();
		}
		$div = $this->_extractOption('div', $options, true);
		if (!$div) {
			return array();
		}

		$divOptions = array('class' => 'input');
		$divOptions = $this->addClass($divOptions, $options['type']);
		
		if (is_string($div)) {
			$divOptions['class'] = $div;
		} elseif (is_array($div)) {
			$divOptions = array_merge($divOptions, $div);
		}
		//if($fieldName != null)
		//	$divOptions = array_merge($divOptions, array('ng-class'=>'{error: '.$this->defaultModel.'.'.$fieldName.'.$invalid}'));
		
		if (
			$this->_extractOption('required', $options) !== false &&
			$this->_introspectModel($this->model(), 'validates', $this->field())
		) {
			$divOptions = $this->addClass($divOptions, 'required');
		}
		if (!isset($divOptions['tag'])) {
			$divOptions['tag'] = 'div';
		}
		return $divOptions;
	}
	
	public function getValidationRules($fieldName, $object)
	{
		$validationRules = '';
		
		if($object && isset($object->validate[$fieldName]) && is_array($object->validate[$fieldName]))
		{
			if(isset($object->validate[$fieldName]['rule']) && isset($object->validate[$fieldName]['message']))
			{
				if(is_array($object->validate[$fieldName]['rule']) ){
					$name = $object->validate[$fieldName]['rule'][0];
				}
				else{
					$name = $object->validate[$fieldName]['rule'];
				}
				
				if(!in_array(strtolower($name), $this->_allowedFields)) {
					$this->_rules[$fieldName]['rule'][] = $object->validate[$fieldName]['rule'];
					$message = $object->validate[$fieldName]['message'];
					$validationRules .= "<li ng-class=".$this->getValidationName($name).">".$message."</li>";
				}
			}
			
			
			foreach($object->validate[$fieldName] as $rules)
			{
				$name = null;
				$message = null;
				if(is_array($rules) && isset($rules['rule']))
				{
					if( is_array($rules['rule']) ){
						$name = $rules['rule'][0];
					}
					else{
						$name = $rules['rule'];
					}
					if(isset($rules['message'])){
						$message = $rules['message'];
					}
					
					if(!in_array(strtolower($name), $this->_allowedFields)) {
					
						$validationRules .= "<li ng-class=".$this->getValidationName($name).">".$message."</li>";
					
						$this->_rules[$fieldName]['rule'][] = $rules['rule'];
					}
				}
			}
		}
		
		$this->_scripts[] = $this->getAngularScript($fieldName);
		
		if($validationRules != '') {
			$validationRules = '<span class="input-help"><ul>'.$validationRules.'</ul></span>';
		}
		
		return $validationRules.'</div>';
	}
	
	public function getValidationNameSize($name)
	{
		$size = null;
		
		switch (strtolower($name)) {
			case 'minlength':
			case 'maxlength':
			case 'custom':
				$size = 2;
			break;
			case 'between':
				$size = 3;
			break;
			default:
				$size = 1;
			break;
		}
		
		return $size;
	}
	
	public function getValidationName($name)
	{
		
		switch (strtolower($name)) {
			case 'notempty':
			case 'required':
				$name = 'required';
			break;
			case 'phone':
			case 'phonenumber':
				$name = 'phone';
			break;
			//case 'minlength':
			//	$name = 'min';
			//case 'maxlength':
			//	$name = 'max';
			//break;
		}
		
		return $name;
	}
	
	public function getAngularScript($fieldName)
	{
		$rulesList = '';
		$rulesListIf = array();
		$embedScript = NULL;
		
		if(!empty($this->_rules)) {
			
			if(isset($this->_rules[$fieldName])) {
				foreach($this->_rules[$fieldName] as $rules)
				{
					foreach($rules as $rule)
					{
						$array = array();
						$ruleName = null;
						
						if(is_array($rule)) {
							$i = 0;
							$max = sizeof($rule);
							while($i < $max) {
							
								$size = $this->getValidationNameSize($rule[$i]); 
									
								switch($size)
								{
									case 1:
										$array = $this->angularScriptRules($rule[$i]);
									break;
									case 2:
										if(isset($rule[$i + 1])) {
											$array = $this->angularScriptRules($rule[$i], $rule[$i + 1]);
										}
									break;
									case 3:
										if(isset($rule[$i + 2])) {
											$array = $this->angularScriptRules($rule[$i], $rule[$i + 1], $rule[$i + 2]);
										}
									break;									
								}
								
								$ruleName = $rule[$i];
								$ruleName = $this->getValidationName($ruleName);
								
								if(isset($array[$ruleName]) && !isset($this->_scripts[$ruleName])){
									$rulesList .= $array[$ruleName];
									$rulesListIf[] = 'scope.'.$ruleName;
								}
								
								$i += $size;
							}
							
						} else {
								$array = $this->angularScriptRules($rule);
								$ruleName = $rule;
								
								$ruleName = $this->getValidationName($ruleName);
								
								if(isset($array[$ruleName]) && !isset($this->_scripts[$ruleName])){
									$rulesList .= $array[$ruleName];
									$rulesListIf[] = 'scope.'.$ruleName;
								}
						}
					}
				}
			}
			
			if(!empty($rulesListIf)) {
				$embedScript = " if(".implode("&&", $rulesListIf).") {
									ctrl.\$setValidity('".$this->correctName($fieldName)."', true);
									return viewValue;
								} else {
									ctrl.\$setValidity('".$this->correctName($fieldName)."', false);                    
									return undefined;
								}";
			}
			
			$script = "					
					app.directive('".$this->correctName($fieldName)."validate', function() {
					return {
						require: 'ngModel',
						link: function(scope, elm, attrs, ctrl) {
							ctrl.\$parsers.unshift(function(viewValue) {
							
							".$rulesList."\n".$embedScript."
								});
							}
						};
					});";
					
			if($rulesList == '')
				return null;
				
			return $script;
		
		}
	}
	
	public static $_regex = array(
		'notempty' => '/[^\s]+/m',
		'email' => '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}$/',
		'alphanumeric' => '/^[a-zA-Z0-9_]+$/',
		'hostname' => '(?:[-_a-z0-9][-_a-z0-9]*\.)*(?:[a-z0-9][-a-z0-9]{0,62})\.(?:(?:[a-z]{2}\.)?[a-z]{2,})',
		'blank' => '/[^\\s]/',
		'card' => '/^(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6011[0-9]{12}|3(?:0[0-5]|[68][0-9])[0-9]{11}|3[47][0-9]{13})$/',
		'time' => '%^((0?[1-9]|1[012])(:[0-5]\d){0,2} ?([AP]M|[ap]m))$|^([01]\d|2[0-3])(:[0-5]\d){0,2}$%',
		'money' => array( 'left' => '/^(?!\x{00a2})\p{Sc}?(?!0,?\d)(?:\d{1,3}(?:([, .])\d{3})?(?:\1\d{3})*|(?:\d+))((?!\1)[,.]\d{2})?$/u',
						  'right' => '/^(?!0,?\d)(?:\d{1,3}(?:([, .])\d{3})?(?:\1\d{3})*|(?:\d+))((?!\1)[,.]\d{2})?(?<!\x{00a2})\p{Sc}?$/u'
					),
		'naturalNumber' => array( 'false'=>'/^(?:0|[1-9][0-9]*)$/',
								  'true' => '/^[1-9][0-9]*$/',
							),
		'phone' => '/^(?:\+?1)?[-. ]?\\(?[2-9][0-8][0-9]\\)?[-. ]?[2-9][0-9]{2}[-. ]?[0-9]{4}$/',
		'postal' => array( 'us' => '/\\A\\b[0-9]{5}(?:-[0-9]{4})?\\b\\z/i',
						   'be' => '/^[1-9]{1}[0-9]{3}$/i',
						   'de' => '/^[0-9]{5}$/i',
						   'it' => '/^[0-9]{5}$/i',
						   'ca' => "/\\A\\b{[ABCEGHJKLMNPRSTVYX]}[0-9]{[ABCEGHJKLMNPRSTVWXYZ]} [0-9]{[ABCEGHJKLMNPRSTVWXYZ]}[0-9]\\b\\z/i"
					),
		'ssn' => array( 'dk' => '/\\A\\b[0-9]{6}-[0-9]{4}\\b\\z/i',
						'nl' => '/\\A\\b[0-9]{9}\\b\\z/i',
						'us' => '/\\A\\b[0-9]{3}-[0-9]{2}-[0-9]{4}\\b\\z/i'
					),
		'uuid' => '/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[1-5][a-fA-F0-9]{3}-[89aAbB][a-fA-F0-9]{3}-[a-fA-F0-9]{12}$/',
		'url' => '/((([A-Za-z]{3,9}:(?:\/\/)?)(?:[-;:&=\+\$,\w]+@)?[A-Za-z0-9.-]+|(?:www.|[-;:&=\+\$,\w]+@)[A-Za-z0-9.-]+)((?:\/[\+~%\/.\w-_]*)?\??(?:[-\+=&;%@.\w_]*)#?(?:[\w]*))?)/'
	);
	
	public function angularScriptRules($rule, $value1 = null, $value2 = null)
	{
		$rule = $this->getValidationName($rule);
		
		switch (strtolower($rule)) {
			case 'notempty':
			case 'required':
				return array( $rule => "scope.$rule = ( viewValue && ".self::$_regex['notempty'].".test(viewValue)) ? 'valid' : undefined;\n");
			break;
			case 'alphanumeric':
			case 'email':
			case 'card':
			case 'time':
			case 'blank':
			case 'uuid':
			case 'url':
				return array($rule => "scope.$rule = ( viewValue && ".self::$_regex[strtolower($rule)].".test(viewValue) ) ? 'valid' : undefined;\n");
			break;
			case 'money':
				$key = ($value1 == 'right') ? 'right' : 'left';
				return array($rule => "scope.$rule = ( viewValue && ".self::$_regex[strtolower($rule)][$key].".test(viewValue) ) ? 'valid' : undefined;\n");
			break;
			case 'custom':
				return array($rule => "scope.$rule = ( viewValue && /".$value1."/.test(viewValue) ) ? 'valid' : undefined;\n");
			break;
			case 'minlength':
				if($value1 != null) {
					return array($rule =>  "scope.$rule = (viewValue && viewValue.length >= ".$value1." ? 'valid' : undefined);\n");
				}
			break;
			case 'maxlength':
				if($value1 != null) {
					return array($rule =>  "scope.$rule = (viewValue && viewValue.length <= ".$value1." ? 'valid' : undefined);\n");
				}
			break;
			case 'phone':
			case 'phonenumber':
				return array($rule => "scope.$rule = ( viewValue && ".self::$_regex['phone'].".test(viewValue) ) ? 'valid' : undefined;\n");
			break;
			case 'between':
				if($value1 != null && $value2 != null) {
					return array($rule =>  "scope.$rule = (viewValue && viewValue.length >= ".$value1." && viewValue.length <= ".$value2." ? 'valid' : undefined);\n");
				}
			break;			
		}
		
		$other = null;
	
		$pattern = '/^confirm|match/';
		
		$ruleTest = strtolower(Inflector::camelize($rule));
		
		preg_match($pattern, $ruleTest, $matches);
		
		if($matches)
		{
			if(strpos( $ruleTest,$matches[0] ) !== false) {
				$other = str_replace($matches[0], '', $ruleTest );
			}
		}
		
		if($other) {
			return array($rule => "scope.$rule = ( viewValue && viewValue == scope.".$other."check ) ? 'valid' : undefined;\n");
		}
	}
	
	public function sanitizeFieldName($name)
	{
		$values = explode(".", $name);
		
		if(is_array($values))
		{
			return $values[sizeof($values)-1];
		}
		
		return $name;
	}
	
	public function end($options = null) {
		$out = null;
		$submit = null;

		if ($options !== null) {
			$submitOptions = array();
			if (is_string($options)) {
				$submit = $options;
			} else {
				if (isset($options['label'])) {
					$submit = $options['label'];
					unset($options['label']);
				}
				$submitOptions = $options;
				
			}
				
			$submitOptions['class'] = 'btn btn-block btn-info discspan2';
			$submitDissabled = $submitOptions;
			$submitOptions['div'] = array('class' => 'controls'); 
			$submitOptions['after'] = '<input class="btn btn-block btn-info discspan2" disabled value="'.$submit.'">';
			
			$out .= $this->submit($submit, $submitOptions);
		}
		if (
			$this->requestType !== 'get' &&
			isset($this->request['_Token']) &&
			!empty($this->request['_Token'])
		) {
			$out .= $this->secure($this->fields);
			$this->fields = array();
		}
		$this->setEntity(null);
		$out .= $this->Html->useTag('formend');
		
		$combined = null;
		
		if(!empty($this->_scripts)) {
		
			$combined = "var app = angular.module('$this->defaultModel', []);\n";
			
			foreach($this->_scripts as $name => $script)
			{
				$combined .= $script;
			}
		}
		
		if($combined) {
			//$out.='<script>'.$this->compressJs($combined).'</script>';
			$out.='<script>'.$combined.'</script>';
		}

		$this->_View->modelScope = false;
		return $out;
	}

	public function correctName($str)
	{
		return strtolower(Inflector::camelize($str));
	}
	
	public function compressJs($buffer) {
        /* remove comments */
        $buffer = preg_replace("/((?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:\/\/.*))/", "", $buffer);
        /* remove tabs, spaces, newlines, etc. */
        $buffer = str_replace(array("\r\n","\r","\t","\n",'  ','    ','     '), '', $buffer);
        /* remove other spaces before/after ) */
        $buffer = preg_replace(array('(( )+\))','(\)( )+)'), ')', $buffer);
        return $buffer;
    }
	
    public function compressStyle($buffer) {
        /* remove comments */
        $buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);
        /* remove tabs, spaces, newlines, etc. */
        $buffer = str_replace(array("\r\n","\r","\n","\t",'     '), '', $buffer);
        /* remove other spaces before/after ; */
        $buffer = preg_replace(array('(( )+{)','({( )+)'), '{', $buffer);
        $buffer = preg_replace(array('(( )+})','(}( )+)','(;( )*})'), '}', $buffer);
        $buffer = preg_replace(array('(;( )+)','(( )+;)'), ';', $buffer);
        return $buffer;
    }
	
	public function getCss()
	{
		return
		'<style>
			/* Validation CSS*/
			.input-help {
			  display: none;
			  position:absolute;
			  z-index: 100;
			  top: -6px;
			  right: 0;
			  width:auto;
			  padding:10px;
			  background:#fefefe;
			  font-size:.875em;
			  border-radius:5px;
			  box-shadow:0 1px 3px #aaa;
			  border:1px solid #ddd;
			  opacity: 0.9;
			}
			.input-help::before {
			  content: "\25C0";
			  position: absolute;
			  top:10px;
			  left:-12px;
			  font-size:16px;
			  line-height:16px;
			  color:#ddd;
			  text-shadow:none;
			}
			.input-help h4 {
			  margin:0;
			  padding:0;
			  font-weight: normal;
			  font-size: 1.1em;
			}

			/* Always hide the input help when it\'s pristine */
			input.ng-pristine + .input-help {
			  display: none;
			}

			/* Hide the invalid box while the input has focus */
			.ng-invalid:focus + .input-help {
			  display: none;
			}

			/* Show a blue border while an input has focus, make sure it overrides everything else */
			/* Overriding Twitter Bootstrap cuz I don\'t agree we need to alarm the user while they\'re typing */
			input:focus {
			  color: black !important;
			  border-color: rgba(82, 168, 236, 0.8) !important;
			  -webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 8px rgba(82, 168, 236, 0.6) !important;
			  -moz-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 8px rgba(82, 168, 236, 0.6) !important;
			  box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 8px rgba(82, 168, 236, 0.6) !important;
			}


			/* Show green border when stuff has been typed in, and its valid */
			.ng-dirty.ng-valid {
			  border-color:#3a7d34;
			}

			/* Show red border when stuff has been typed in, but its invalid */
			.ng-dirty.ng-invalid {
			  border-color:#ec3f41;
			}

			/* Show the help box once it has focus */
			.immediate-help:focus + .input-help {
			  display: block;
			}

			/* Show the help box once it has focus */
			.immediate-help:focus + div+ .input-help {
			  display: block;
			}

			/* Immediate help should be red when pristine */
			.immediate-help.ng-pristine:focus + .input-help {
			  border-color:#ec3f41;
			}
			.immediate-help.ng-pristine:focus + .input-help::before {
			  color:#ec3f41;
			}

			/* Help hould be green when input is valid */
			.ng-valid + .input-help {
			  border-color:#3a7d34;
			}
			.ng-valid + .input-help::before {
			  color:#3a7d34;
			}

			/* Help should show and be red when invalid */
			.ng-invalid + .input-help {
			  display: block;
			  border-color: #ec3f41;
			}
			.ng-invalid + .input-help::before {
			  color: #ec3f41;
			}

			/* Style input help requirement bullets */
			.input-help ul {
			  list-style: none;
			  margin: 10px 0 0 0;
			}

			/* Default each bullet to be invalid with a red cross and text */
			.input-help li {
			  padding-left: 22px;
			  line-height: 24px;
			  color:#ec3f41;
			  background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAA1CAYAAABIkmvkAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAAJwAAACcBKgmRTwAAABZ0RVh0Q3JlYXRpb24gVGltZQAxMC8wOS8xMlhq+BkAAAAcdEVYdFNvZnR3YXJlAEFkb2JlIEZpcmV3b3JrcyBDUzVxteM2AAAEA0lEQVRIie2WW2jbVRzHv//zT5rkn0ub61zaNdEiPqTC9EFRhtgJk63qg3Wr0806pswNiYgDUXxwyryCEB8UBevtaSCCDHQoboKyoVvVzfRmL2napU0mrdbl8s//dy4+dM1M28n64FsPnIdz+XzO75zfOXA0pRRWU7o/uS9FxOc+3/vlIQBgq4F3fHxvKuIPJ9cFwi9uTXU8BwDa1Uaw/aN7UusCkWRbPI5yxcTI2Bgy49kXrkrwwIedqYg/nGyLXwsJiYHBYWTGs7Cq5Kpt4cA3PXft+2rX40vhrt7OVLgplIzHYuBKoH9gCKMjGVE1LdfJl86YDAAOfN2ziZP4NODyv9/z2fanFuH7P9iWCjcFk/FYK4QSGLgEk0WeUy/3mQCgPXFs9xbBRW883NrssDvQN3hWcOLPEPGWiD94MBaPQymBoaERjI9mBSfu+fHwL+biItpjR3e6JFfloDeAaGQ9SpUycvlp6ExHJBKGYsDvgyMYH81KTsL90yuX4VoWdh3pMqSQpWBjAC3RZkgpYEkCFDA8NIqJ0UlFxI3Tr/5aB9elsau305BcloKBAFpjLeBSYGRwDBNjk4oTN06/dnYZXCcAgK1vbzYkl6VwOATihOzYlOLEjTOvn1sRXiYAgDsP32YIKUuWaXFOwtP3xrnqleAVBQBwy/M3GZy4+PnN3/4TvqJgNWVVj2lNsCZYE6wJ1gRrgv9dYAMAHHw2Bl2fUEpBVavtLPVW/78nVR/Zk4CupzVHA6zChSOK0yHv0S8GFyK4BMPhAJxOgLE03/9kYhE2dz+agKaldY8bDaEQ7D5ft7Roy+UIlCooy5LQdaZ5vVBEgGmmrT172yVxaIylmdcDm9cHc2oK1Zm8kETvLAo0pRRk8mmnEqKouVw68zVCzP8F/uccFHHoXi/sjT6Y53Mw83mhOHn8J7416wQAwPftd0ouiswwdJu/CRASkBKQAmYuBzNfWIC/O173W6llwfbeu6Yi8tDsrAQJYGICyGQAIWDO5KUkaxlcJwAASdSmaWAQHCACOAc4h6YzJi1qWymNNUHlwYcT0JDWXQbACYhGgeh6gHM4Ghuh2/R0YePNiaUCTSmFcvdDCY1paZvhht3nQ2VmGmahICSR5vQHmDt6DcozeZSnp2FdLLZHhwdq94SVd+xMaJqWtrkM2L1uVHILpy0t8igidymXExfHMzBCQbhCIdga7Onz8etqkdgkUYTZbYCSqORmULlQEIq4J3jyexMA8jdu9BRzuaKyLN3udkNjDEqICID+2hbm797Wwez24/T3vJTE3aFTP9Sd9vT1NziVEMUGr1c35+Y2b5jKnqgNKqWglMLspjs6/rj1dudie2mdao07J5s3dCzt/werJTyI1yYqpQAAAABJRU5ErkJggg==) no-repeat  2px -34px;
			}

			/* Set to green check and text when valid */
			.input-help li.valid {
			  color:#3a7d34;
			  background-position: 2px 6px;
			}

			/* Set submit button */
			form .btn, form.ng-valid .btn[disabled] {
			  display: none;
			}
			form.ng-invalid .btn[disabled], form.ng-valid .btn {
			  display: inline-block;
			}

			.form-horizontal .control-label {
				width: 100px;
			}
			.form-horizontal .controls {
			  position: relative;
			  margin-left: 120px;
			}
			</style>';
	}
	
}
