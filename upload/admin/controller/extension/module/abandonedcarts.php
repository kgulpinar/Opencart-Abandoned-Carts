<?php
class ControllerExtensionModuleAbandonedcarts extends Controller {
    private $moduleName;
    private $modulePath;
    private $moduleModel;
	private $moduleVersion;
    private $extensionsLink;
    private $callModel;
    private $vendorFolder;
    private $eventGroup = "abandonedcarts";
    private $error  = array(); 
    private $data   = array();
    
    public function __construct($registry) {
        parent::__construct($registry);
        
        // Config Loader
        $this->config->load('isenselabs/abandonedcarts');
        
        // Token
        $this->data['token_string'] = $this->config->get('abandonedcarts_token_string');
        $this->data['token']        = $this->session->data[$this->data['token_string']];
        $this->data['token_addon']  = $this->data['token_string'] . '=' . $this->data['token'];
        
        /* Fill Main Variables - Begin */
        $this->moduleName           = $this->config->get('abandonedcarts_name');
        $this->callModel            = $this->config->get('abandonedcarts_model');
        $this->modulePath           = $this->config->get('abandonedcarts_path');
        $this->moduleVersion        = $this->config->get('abandonedcarts_version');        
        $this->extensionsLink       = $this->url->link($this->config->get('abandonedcarts_link'), $this->data['token_addon'] . $this->config->get('abandonedcarts_link_params'), 'SSL');
        $this->vendorFolder         = $this->config->get('abandonedcarts_vendor_folder');
        /* Fill Main Variables - End */
                
        // Load Model
        $this->load->model($this->modulePath);
        
        // Model Instance
        $this->moduleModel          = $this->{$this->callModel};
        
        // Multi-Store
        $this->load->model('setting/store');
        // Settings
        $this->load->model('setting/setting');
        // Multi-Lingual
        $this->load->model('localisation/language');
        
        // Languages
        $this->language->load($this->modulePath);
		$language_strings = $this->language->load($this->modulePath);
        foreach ($language_strings as $code => $languageVariable) {
			$this->data[$code] = $languageVariable;
		}
        
        // Variables
        $this->data['moduleName']   = $this->moduleName;
        $this->data['modulePath']   = $this->modulePath;
        $this->data['vendorFolder'] = $this->vendorFolder;
    }
	
	// Main controller
	public function index() {
		// Title
		$this->document->setTitle($this->language->get('heading_title') . ' ' . $this->moduleVersion);

		// Styles		
		$this->document->addStyle('view/stylesheet/'.$this->moduleName.'/'.$this->moduleName.'.css');	
        
        // Scripts
        $this->document->addScript('view/javascript/'.$this->moduleName.'/'.$this->moduleName.'.js');	
		$this->document->addScript('view/javascript/'.$this->moduleName.'/cron.js');
		$this->document->addScript('view/javascript/'.$this->moduleName.'/nprogress.js');
        
        // Summernote - 2.3.x
        $this->document->addScript('view/javascript/summernote/summernote.min.js');
        $this->document->addStyle('view/javascript/summernote/summernote.css');
		
		// Store data
		if(!isset($this->request->get['store_id'])) {
           $this->request->get['store_id'] = 0;
        } 
		$store = $this->getCurrentStore($this->request->get['store_id']);
		
		// New fields
		$check_update = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "".$this->moduleName."` LIKE 'notified'");
		if (!$check_update->rows) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "".$this->moduleName."` ADD `notified` SMALLINT NOT NULL DEFAULT 0");
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "".$this->moduleName."` ADD `ordered` TINYINT NOT NULL DEFAULT 0");
		}
		
		// Saving data		
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			if (!empty($this->request->post['OaXRyb1BhY2sgLSBDb21'])) {
				$this->request->post[$this->moduleName]['LicensedOn'] = $this->request->post['OaXRyb1BhY2sgLSBDb21'];
			}
			if (!empty($this->request->post['cHRpbWl6YXRpb24ef4fe'])) {
				$this->request->post[$this->moduleName]['License'] = json_decode(base64_decode($this->request->post['cHRpbWl6YXRpb24ef4fe']),true);
			}
			$store = $this->getCurrentStore($this->request->post['store_id']);
			
			$this->model_setting_setting->editSetting($this->moduleName, $this->request->post, $this->request->post['store_id']);
            
            $module_status = array(
                'group' => $this->config->get('abandonedcarts_status_group'),
                'value' => $this->config->get('abandonedcarts_status_value')
            );
            
            $this->model_setting_setting->editSetting($module_status['group'], array($module_status['value'] => '1'));

			$this->session->data['success'] = $this->language->get('text_success');

			if ($this->request->post[$this->moduleName]["ScheduleEnabled"] == 'yes') {
                $this->editCron($this->request->post, $store['store_id']);
            }
			
			$this->response->redirect($this->url->link($this->modulePath, $this->data['token_addon'] . '&store_id=' . $store['store_id'], 'SSL'));
		}
		
		// Sucess & Error messages
		if (isset($this->session->data['success'])) {
			$this->data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$this->data['success'] = '';
		}
		
		if (isset($this->error['warning'])) {
			$this->data['error_warning'] = $this->error['warning'];
		} else {
			$this->data['error_warning'] = '';
		}
        
		$this->data['heading_title'] = $this->language->get('heading_title') . ' ' . $this->moduleVersion;	
		
		// Breadcrumbs
  		$this->data['breadcrumbs'] = array();
   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/dashboard', $this->data['token_addon'], 'SSL'),
      		'separator' => false
   		);
   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_module'),
			'href'      => $this->extensionsLink,
      		'separator' => ' :: '
   		);
		
   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link($this->modulePath, $this->data['token_addon'], 'SSL'),
      		'separator' => ' :: '
   		);

		// Variables
		$this->data['currency']				    = $this->config->get('config_currency');
		$this->data['stores']					= array_merge(array(0 => array('store_id' => '0', 'name' => $this->config->get('config_name') . '&nbsp;' . $this->data['text_default'].'', 'url' => HTTP_SERVER, 'ssl' => HTTPS_SERVER)), $this->model_setting_store->getStores());
		$languages						        = $this->model_localisation_language->getLanguages();
		$this->data['languages']				= $languages;
		$firstLanguage					        = array_shift($languages);
		$this->data['firstLanguageCode']		= $firstLanguage['code'];
		$this->data['store']					= $store;
		$this->data['action']					= $this->url->link($this->modulePath, $this->data['token_addon'], 'SSL');
		$this->data['cancel']					= $this->extensionsLink;
		$this->data['moduleSettings']			= $this->model_setting_setting->getSetting($this->moduleName, $store['store_id']);
        $this->data['moduleData']				= (isset($this->data['moduleSettings'][$this->moduleName])) ? $this->data['moduleSettings'][$this->moduleName] : array();
		$this->data['usedCoupons']			    = $this->moduleModel->getTotalUsedCoupons(); 
		$this->data['givenCoupons']			    = $this->moduleModel->getTotalGivenCoupons();
		$this->data['registeredCustomers']	    = $this->moduleModel->getTotalRegisteredCustomers();
		$this->data['totalCustomers']			= $this->moduleModel->getTotalCustomers();
		$this->data['mostVisitedPages']		    = $this->moduleModel->getMostVisitedPages();
		$this->data['e_mail']					= $this->config->get('config_email');
		
		$this->data['cronPhpPath'] = '0 0 * * * ';
		if (function_exists('shell_exec') && trim(shell_exec('echo EXEC')) == 'EXEC') {
			$this->data['cronPhpPath'] .= shell_exec("which php"). ' ';
        } else {
			$this->data['cronPhpPath'] .= 'php ';
        }
		$this->data['cronPhpPath'] .= dirname(DIR_APPLICATION) . '/' . $this->vendorFolder . '/'.$this->moduleName.'/sendReminder.php';

        $this->data['encodedLicense'] =  "";
        $this->data['licenseExpireDate'] = "";
        $this->data['ticketOpenLink'] = "";

        $this->data['http_catalog'] = HTTP_CATALOG;

		
		// Template data
		$this->data['header']					= $this->load->controller('common/header');
		$this->data['column_left']			    = $this->load->controller('common/column_left');
		$this->data['footer']					= $this->load->controller('common/footer');
		
		// Template load
		$this->response->setOutput($this->load->view($this->modulePath.'/'.$this->moduleName, $this->data));
	}
	
	// Get mail template data
	public function get_mailtemplate_settings() {
 		$this->data['currency']                 = $this->config->get('config_currency');	
		$this->data['languages']                = $this->model_localisation_language->getLanguages();
		$this->data['mailtemplate']['id']       = $this->request->get['mailtemplate_id'];
		$store_id								= $this->request->get['store_id'];
		$this->data['data']                     = $this->model_setting_setting->getSetting($this->moduleName, $store_id);
		$this->data['moduleName']               = $this->moduleName;
		$this->data['moduleData']               = (isset($this->data['data'][$this->moduleName])) ? $this->data['data'][$this->moduleName] : array();
		$this->data['newAddition']              = true;
		
		$this->response->setOutput($this->load->view($this->modulePath.'/tab_mailtab', $this->data));
	}
	
	// Validation against users with no permissions	
	protected function validateForm() {
		if (!$this->user->hasPermission('modify', $this->modulePath)) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		return !$this->error;
	}
	
	// Function that edits the cron jobs
	private function editCron($data=array(), $store_id) {
		if (function_exists('shell_exec') && trim(shell_exec('echo EXEC')) == 'EXEC') {
			$phpPath = str_replace(PHP_EOL, '', shell_exec("which php"));
        } else {
            $phpPath = 'php';
        }
        
        if (empty($phpPath)) {
            $phpPath = 'php';
        }
		
        $cronCommands   = array();
        $cronFolder     = dirname(DIR_APPLICATION) . '/' . $this->vendorFolder . '/'.$this->moduleName.'/';
        $dateForSorting = array();
        if (isset($data[$this->moduleName]["ScheduleType"]) && $data[$this->moduleName]["ScheduleType"] == 'F') {
            if (isset($data[$this->moduleName]["FixedDates"])) {
                foreach ($data[$this->moduleName]["FixedDates"] as $date) {
                    $buffer           = explode('/', $date);
                    $bufferDate       = explode('.', $buffer[0]);
                    $bufferTime       = explode(':', $buffer[1]);
                    $cronCommands[]   = (int) $bufferTime[1] . ' ' . (int) $bufferTime[0] . ' ' . (int) $bufferDate[0] . ' ' . (int) $bufferDate[1] . ' * '.$phpPath.' ' . $cronFolder . 'sendReminder.php';
                    $dateForSorting[] = $bufferDate[2] . '.' . $bufferDate[1] . '.' . $bufferDate[0] . '.' . $buffer[1];
                }
                asort($dateForSorting);
                $sortedDates = array();
                foreach ($dateForSorting as $date) {
                    $newDate       = explode('.', $date);
                    $sortedDates[] = $newDate[2] . '.' . $newDate[1] . '.' . $newDate[0] . '/' . $newDate[3];
                }
                $data = $sortedDates;
            }
        }
        if (isset($data[$this->moduleName]["ScheduleType"]) && $data[$this->moduleName]["ScheduleType"] == 'P') {
            $cronCommands[] = $data[$this->moduleName]['PeriodicCronValue'] . ' '.$phpPath.' ' . $cronFolder . 'sendReminder.php';
        }
        if (isset($cronCommands)) {
            $cronCommands      = implode(PHP_EOL, $cronCommands);
            $currentCronBackup = shell_exec('crontab -l');
            $currentCronBackup = explode(PHP_EOL, $currentCronBackup);
            foreach ($currentCronBackup as $key => $command) {
                if (strpos($command, $phpPath.' ' . $cronFolder . 'sendReminder.php') || empty($command)) {
                    unset($currentCronBackup[$key]);
                }
            }
            $currentCronBackup = implode(PHP_EOL, $currentCronBackup);
            file_put_contents($cronFolder . 'cron.txt', $currentCronBackup . PHP_EOL . $cronCommands . PHP_EOL);
            exec('crontab -r');
            exec('crontab ' . $cronFolder . 'cron.txt');
        }
    }
	
	// Gets catalog url
	private function getCatalogURL(){
        if (isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on') || ($_SERVER['HTTPS'] == '1'))) {
            $storeURL = HTTPS_CATALOG;
        } else {
            $storeURL = HTTP_CATALOG;
        } 
        return $storeURL;
    }
	
	// Install module
	public function install() {
	    $this->moduleModel->install();
        $this->load->model("setting/event");
        $this->model_setting_event->deleteEventByCode($this->eventGroup);
        $eventsPath = $this->config->get('abandonedcarts_events_path');

        // Admin events
        $this->model_setting_event->addEvent($this->eventGroup, "admin/view/common/column_left/before", $this->modulePath . "/injectAdminMenuItem");

        // Catalog events
        $this->model_setting_event->addEvent($this->eventGroup, "catalog/*/before", $eventsPath . "/init");
        $this->model_setting_event->addEvent($this->eventGroup, "catalog/controller/extension/payment/*/confirm/before", $eventsPath . "/onBeforePaymentConfirm");
    }
	
	// Uninstall module
	public function uninstall() {
		$this->model_setting_setting->deleteSetting($this->moduleName,0);
		$stores = $this->model_setting_store->getStores();
		foreach ($stores as $store) {
			$this->model_setting_setting->deleteSetting($this->moduleName, $store['store_id']);
		}
        $this->moduleModel->uninstall();

        $this->load->model("setting/event");
        $this->model_setting_event->deleteEventByCode($this->eventGroup);
    }

    // Events
    public function injectAdminMenuItem($eventRoute, &$data) {
        if ($this->user->hasPermission('access', $this->modulePath)) {
            $this->load->model('setting/setting');
            $abandonedCartsSettings = $this->model_setting_setting->getSetting('abandonedcarts', $this->config->get('store_id'));
            
            if (isset($abandonedCartsSettings['abandonedcarts']['Enabled']) && $abandonedCartsSettings['abandonedcarts']['Enabled']=='yes' && isset($abandonedCartsSettings['abandonedcarts']['MenuWidget']) && $abandonedCartsSettings['abandonedcarts']['MenuWidget']=='yes') { 
                $AB_count = $this->db->query("SELECT count(*) as total FROM `" . DB_PREFIX . "abandonedcarts` WHERE `notified`=0");
                
                $data['menus'][] = array(
                    'id'       => 'menu-abandonedcarts',
                    'icon'	   => 'fa fa-shopping-cart fa-fw', 
                    'name'	   => 'Abandoned Carts <span class="label label-danger">'. $AB_count->row['total'] . '</span>',
                    'href'     => $this->url->link('extension/module/abandonedcarts', $this->data['token_addon'], true),
                    'children' => array()
                );	
            }
        }
    }
	
	// Get the current abandoned carts
	public function getabandonedcarts() {
        if (!empty($this->request->get['page'])) {
            $page = (int) $this->request->get['page'];
        } else {
			$page = 1;	
		}
			
		if(!isset($this->request->get['store_id'])) {
           $this->request->get['store_id'] = 0;
        } 
		
		if (isset($this->request->get['notified']) && $this->request->get['notified']=='1') {
		   	$this->data['notified'] = true;
		   	$this->data['forFilter'] = 'notified';
        } else {
			$this->data['notified'] = false;
		  	$this->data['forFilter'] = 'default';
		}
		
		if(isset($this->request->get['ordered']) && $this->request->get['ordered']=='1') {
		   	$this->data['ordered'] = true;
		  	$this->data['forFilter'] = 'ordered';
        } else {
			$this->data['ordered'] = false;
		}
        
		$url_link = '&store_id='.$this->request->get['store_id'];
		$this->data['filterURL'] = $this->url->link($this->modulePath . '/getabandonedcarts', $this->data['token_addon'] . $url_link, 'SSL');
		
		$this->load->model('tool/image');
		
        $this->load->model('customer/customer');
        $this->data['customer_url'] = 'customer/customer/edit';
		
		$this->data['model_tool_image'] 	    = $this->model_tool_image;
		$this->data['thiscurrency'] 			= $this->currency;
		$this->data['config_currency']          = $this->config->get('config_currency');
		$this->data['thisurl']                  = $this->url;
		$this->data['store_id']			        = $this->request->get['store_id'];
        $this->data['store_data']               = $this->getCurrentStore($this->request->get['store_id']);
        $url_data                               = parse_url($this->data['store_data']['url']);
        $this->data['last_accessed_url']        = $url_data['scheme']."://".$url_data['host'];
		$this->data['limit']				    = 8; // $this->config->get('config_limit_admin')
		$this->data['total']				    = $this->moduleModel->getTotalAbandonedCarts($this->data['store_id'], $this->data['notified'], $this->data['ordered']);
		$moduleSettings				            = $this->model_setting_setting->getSetting($this->moduleName, $this->data['store_id']);
		$moduleSettings				            = (isset($moduleSettings[$this->moduleName])) ? $moduleSettings[$this->moduleName] : array();
		
        
		$this->data['usable_templates']	= array();
		if (isset($moduleSettings['MailTemplate']) && sizeof($moduleSettings['MailTemplate'])>0) {
			foreach ($moduleSettings['MailTemplate'] as $template) {
				if (isset($template['Enabled']) && $template['Enabled']=='yes') {
					$this->data['usable_templates'][$template['id']] = $template['Name'];
				}
			}
		}
		
		if (sizeof($this->data['usable_templates'])==0) {
			$this->data['usable_templates'][0] = 'No active template!';
		}
		
	    $pagination					= new Pagination();
        $pagination->total			= $this->data['total'];
        $pagination->page			= $page;
        $pagination->limit			= $this->data['limit']; 
        $pagination->url			= $this->url->link($this->modulePath.'/getabandonedcarts', $this->data['token_addon'] . '&page={page}&store_id=' . $this->data['store_id'] . '&notified=' . $this->data['notified'] . '&ordered=' . $this->data['ordered'], 'SSL');
		$this->data['pagination']   = $pagination->render();
        $this->data['sources']      = $this->moduleModel->viewAbandonedCarts($page, $this->data['limit'], $this->data['store_id'],  $this->data['notified'], $this->data['ordered']);

        foreach ($this->data['sources'] as &$ab) {
            $ab['customer_info'] = json_decode($ab['customer_info'], true);
            $ab['customerCheck'] = !empty($ab['customer_info']['email']) ? $this->model_customer_customer->getCustomerByEmail($ab['customer_info']['email']) : false;

            $ab['cart'] = json_decode($ab['cart'], true);
            $total_price = 0;
            $total_quantity = 0;

            foreach ($ab['cart'] as &$product) { 
                if ($product['image']) {
                    $product['image_thumb'] = $this->model_tool_image->resize($product['image'], 30, 30);
                    $product['bigger_thumb'] = $this->model_tool_image->resize($product['image'], 125, 125);
                } else {
                    $product['image_thumb'] = false;
                    $product['bigger_thumb'] = false;
                }
                $product['price_formatted'] = $this->currency->format($product['price'], $this->config->get('config_currency'));

                $total_quantity += $product['quantity'];
                $total_price += $product['quantity']*$product['price'];
            }

            $ab['cart_total_price'] = $total_price;
            $ab['cart_total_price_formatted'] = $this->currency->format($total_price, $this->config->get('config_currency'));
            $ab['cart_total_quantity'] = $total_quantity;
            $ab['time_spent'] = gmdate("H:i:s", strtotime($ab['date_modified']) - strtotime($ab['date_created']));
        }

		$this->data['results'] = sprintf($this->language->get('text_pagination'), ($this->data['total']) ? (($page - 1) * $this->data['limit']) + 1 : 0, ((($page - 1) * $this->data['limit']) > ($this->data['total'] - $this->data['limit'])) ? $this->data['total'] : ((($page - 1) * $this->data['limit']) + $this->data['limit']), $this->data['total'], ceil($this->data['total'] / $this->data['limit']));

		$this->response->setOutput($this->load->view($this->modulePath.'/view_abandonedcarts', $this->data));
    }

	// Send reminder popup show
	public function sendreminder() {
		$this->load->model('tool/image');
		
		$this->data['newAddition']			= true;
		$languages						    = $this->model_localisation_language->getLanguages();
		$this->data['languages']            = $languages;
		$firstLanguage					    = array_shift($languages);
		$this->data['firstLanguageCode']    = $firstLanguage['code'];
		$this->data['data']                 = $this->model_setting_setting->getSetting($this->moduleName, $this->request->get['store_id']);
		$this->data['id']                   = $this->request->get['id'];
		$this->data['store_id']				= $this->request->get['store_id'];
		$this->data['currency']				= $this->config->get('config_currency');
		$this->data['result']               = $this->moduleModel->getCartInfo($this->data['id']);
		$this->data['result']['customer_info']= isset($this->data['result']['customer_info']) ? json_decode($this->data['result']['customer_info'], true) : array(); 
		$this->data['language_id']			= isset($this->data['result']['customer_info']['language']) ? $this->moduleModel->getLanguageId($this->data['result']['customer_info']['language']) : $this->config->get('config_language_id');
		$this->data['mailtemplate']['id']   = $this->request->get['template_id'];
		$this->data['moduleData']           = (isset($this->data['data'][$this->moduleName])) ? $this->data['data'][$this->moduleName] : array();
    
		$this->response->setOutput($this->load->view($this->modulePath.'/send_reminder', $this->data));
	}
	
	// Send manual email to the customers
	public function sendcustomemail() {
		if (isset($this->request->post) && isset($this->request->post['ABcart_id']) && isset($this->request->post['AB_template_id'])) {
			$this->load->model('marketing/coupon');
			$this->load->model('tool/image');
			$this->load->model('catalog/product');
			
			/*require_once(DIR_SYSTEM.'library/tax.php');
			require_once(DIR_SYSTEM.'library/customer.php');*/
			
			$result							= $this->moduleModel->getCartInfo($this->request->post['ABcart_id']);
			$this->request->get['store_id'] = $result['store_id'];
			$template_id					= $this->request->post['AB_template_id'];
			$language_id					= $this->request->post['AB_language_id'];
			$setting						= $this->model_setting_setting->getSetting($this->moduleName, $result['store_id']);
			$moduleData						= (isset($setting[$this->moduleName])) ? $setting[$this->moduleName] : array();
			$settingTemplate				= $this->request->post[$this->moduleName]['MailTemplate'][$template_id];
			$result['customer_info']		= json_decode($result['customer_info'], true);
			$Message						= html_entity_decode($settingTemplate['Message'][$language_id]);
			$width							= $settingTemplate['ProductWidth'];
			$height							= $settingTemplate['ProductHeight'];
			$skipOutOfStock					= (!empty($settingTemplate['SkipOutOfStock']) && $settingTemplate['SkipOutOfStock'] == 'yes');
			$result['cart']					= json_decode($result['cart'], true);
			$catalog_link					= "";
			$store_data						= $this->getCurrentStore($result['store_id']);
			$catalog_link					= $store_data['url'];

			$this->load->model('localisation/language');
			$OrderLanguage = $this->model_localisation_language->getLanguage($language_id);
			$LangVars =  $this->moduleModel->loadLanguage($OrderLanguage['directory'],'abandonedcarts');

            $availableProductsCount = 0;
			
			$CartProducts = '<table style="width:100%">';
			$CartProducts .= '<tr>
								  <td class="left" width="70%"><strong>'.$LangVars['text_product'].'</strong></td>
								  <td class="left" width="15%"><strong>'.$LangVars['text_qty'].'</strong></td>
								  <td class="left" width="15%"><strong>'.$LangVars['text_price'].'</strong></td>
								</tr>';
			foreach ($result['cart'] as $product) { 
                $product_info = $this->model_catalog_product->getProduct($product['product_id']);

                if ((int)$product_info["quantity"] <= 0 && $skipOutOfStock) {
                    continue;
                }

                $availableProductsCount++;

				if ($product['image']) {
					$image_thumb = $this->model_tool_image->resize($product['image'], $width, $height);
				} else {
					$image = false;
				}
				$CartProducts .='<tr>';
				$CartProducts .='<td><div style="float:left;padding-right:3px;"><a href="'.$catalog_link.'index.php?route=product/product&product_id='. $product['product_id'].'" target="_blank"><img src="'.str_replace(' ', '%20', $image_thumb).'" /></a></div> <a href="'.$catalog_link.'index.php?route=product/product&product_id='. $product['product_id'].'" target="_blank">'.$product['name'].'</a><br />';
				foreach ($product['option'] as $option) {
                       $CartProducts .= '- <small>'.$option['name'].' '.$option['value'].'</small><br />';
                }

				if ($moduleData['Taxes']=='yes') {
					//$this->customer->login($result['customer_info']['email'],'123',true);
					$price = $this->tax->calculate($product['price'], $product_info['tax_class_id'], $this->config->get('config_tax'));
					//$this->customer->logout();
				} else {
					$price = $product['price'];
				}
				//
                $CartProducts .= '</td>
                          <td>x&nbsp;'.$product['quantity'].'</td>
						  <td>'.($this->currency->format($price, $this->config->get('config_currency'))).'</td>
                        </tr>';
			}
			$CartProducts .='</table>';

            if (!$availableProductsCount && $skipOutOfStock) {
                header("HTTP/1.1 500 Internal Server Error");
                echo "Error: None of the products in the cart are in stock!";
                return;
            }
			
			if ($settingTemplate['DiscountType']=='N') {
				// do nothing here
			} else {
				if ($settingTemplate['DiscountApply']=='all_products') {
					$DiscountCode		= $this->moduleModel->generateuniquerandomcouponcode();
					$TimeEnd			=  time() + $settingTemplate['DiscountValidity'] * 24 * 60 * 60;
					$CouponData			= array('name' => 'AbCart [' . $result['customer_info']['email'].']',
					'code'				=> $DiscountCode, 
					'discount'			=> $settingTemplate['Discount'],
					'type'				=> $settingTemplate['DiscountType'],
					'total'		   		=> $settingTemplate['TotalAmount'],
					'logged'			=> $settingTemplate['DiscountCustomerLogin'],
					'shipping'			=> $settingTemplate['DiscountShipping'],
					'date_start'        => date('Y-m-d', time()),
					'date_end'			=> date('Y-m-d', $TimeEnd),
					'uses_total'	  	=> '1',
					'uses_customer'   	=> '1',
					'status'		  	=> '1');
					$this->model_marketing_coupon->addCoupon($CouponData);
				} else if ($settingTemplate['DiscountApply']=='cart_products') {
					$cart_products		= array();
					foreach ($result['cart'] as $product) { 
					  $cart_products[]  = $product['product_id'];
					}
					$DiscountCode		= $this->moduleModel->generateuniquerandomcouponcode();
					$TimeEnd			=  time() + $settingTemplate['DiscountValidity'] * 24 * 60 * 60;
					$CouponData	   		= array('name' => 'AbCart [' . $result['customer_info']['email'].']',
					'code'				=> $DiscountCode, 
					'discount'			=> $settingTemplate['Discount'],
					'type'				=> $settingTemplate['DiscountType'],
					'total'		  		=> $settingTemplate['TotalAmount'],
					'logged'			=> $settingTemplate['DiscountCustomerLogin'],
					'shipping'			=> $settingTemplate['DiscountShipping'],
					'coupon_product' 	=> $cart_products,
					'date_start'	  	=> date('Y-m-d', time()),
					'date_end'			=> date('Y-m-d', $TimeEnd),
					'uses_total'	  	=> '1',
					'uses_customer'   	=> '1',
					'status'		  	=> '1');
					$this->model_marketing_coupon->addCoupon($CouponData);
				}
			}
			
			$patterns = array();
			$patterns[0] = '{firstname}';
			$patterns[1] = '{lastname}';
			$patterns[2] = '{cart_content}';
			if (!($settingTemplate['DiscountType']=='N')) {
				$patterns[3] = '{discount_code}';
				$patterns[4] = '{discount_value}';
				$patterns[5] = '{total_amount}';
				$patterns[6] = '{date_end}';
			}
			$patterns[7] = '{unsubscribe_link}';
            $patterns[8] = '{restore_link}';
			$replacements = array();
			$replacements[0] = $result['customer_info']['firstname'];
			$replacements[1] = $result['customer_info']['lastname'];
			$replacements[2] = $CartProducts;
			if (!($settingTemplate['DiscountType']=='N')) {
				$replacements[3] = $DiscountCode;
				$replacements[4] = $settingTemplate['Discount'];
				$replacements[5] = $settingTemplate['TotalAmount'];
				$replacements[6] = date($moduleData['DateFormat'], $TimeEnd);
			}
			$replacements[7] = '<a href="'.$catalog_link.'index.php?route=' . $this->modulePath . '/removeCart&id='.$this->request->post['ABcart_id'].'">'.$LangVars['text_unsubscribe'].'</a>';
            $replacements[8] = '<a href="'.$catalog_link.'index.php?route=' . $this->modulePath . '/restoreCart&id='.$this->request->post['ABcart_id'].'">'.$LangVars['text_restorecart'].'</a>';

			$HTMLMail = str_replace($patterns, $replacements, $Message);
            
            $patterns_subject = array();
            $patterns_subject[0] = '{firstname}';
			$patterns_subject[1] = '{lastname}';
            $replacements = array();
			$replacements_subject[0] = $result['customer_info']['firstname'];
			$replacements_subject[1] = $result['customer_info']['lastname'];
            $HTMLSubject =  str_replace($patterns_subject, $replacements_subject, $settingTemplate['Subject'][$language_id]);
			
            $MailData = array(
				'email' =>  $result['customer_info']['email'],
				'message' => $HTMLMail, 
				'subject' => $HTMLSubject,
				'store_id' => $result['store_id'],
                'store_email' => $store_data['store_email']
            );
			
            $emailResult = $this->moduleModel->sendMail($MailData);
			$run_query = $this->db->query("UPDATE `" . DB_PREFIX . "".$this->moduleName."` SET notified = (notified + 1) WHERE `id`=".(int)$this->request->post['ABcart_id']);
 			if ($emailResult) {
				echo "The email is sent successfully.";
				if (isset($settingTemplate['RemoveAfterSend']))
					$run_query = $this->db->query("DELETE FROM `" . DB_PREFIX . "".$this->moduleName."` WHERE `id`=".(int)$this->request->post['ABcart_id']);
			} else {
				echo "There is an error with the provided data in the form below.";
			}
		} else {
			echo "There is an error with the provided data in the form below.";	
		}
	}
	
	// Function that tests cron functionality
	public function testcron()
    {
        if (function_exists('shell_exec') && trim(shell_exec('echo EXEC')) == 'EXEC') {
            $this->data['shell_exec_status'] = 'Enabled';
        } else {
            $this->data['shell_exec_status'] = 'Disabled';
        }

        $cronFolder = dirname(DIR_APPLICATION) . '/' . $this->vendorFolder . '/'.$this->moduleName.'/';

        if (file_exists($cronFolder . 'cron.txt')) {
            $this->data['folder_permission'] = "Writable";
            unlink($cronFolder . 'cron.txt');
        } else {
            $this->data['folder_permission'] = "Unwritable";
        }

        if ($this->data['shell_exec_status'] == 'Enabled') {
		   
            if (shell_exec('crontab -l')) {
                $this->data['cronjob_status']    = 'Enabled';
                $curentCronjobs                  = shell_exec('crontab -l');
                $this->data['current_cron_jobs'] = explode(PHP_EOL, $curentCronjobs);
                file_put_contents($cronFolder . 'cron.txt', '* * * * * echo "test" ' . PHP_EOL);
            } else {
				file_put_contents($cronFolder . 'cron.txt', '* * * * * echo "test" ' . PHP_EOL);
                if (file_exists($cronFolder . 'cron.txt')) {
                    exec('crontab ' . $cronFolder . 'cron.txt');
                    if (shell_exec('crontab -l')) {
                        $this->data['cronjob_status'] = 'Enabled';
                        shell_exec('crontab -r');
                    } else {
                        $this->data['cronjob_status'] = 'Disabled';
                    }
                }
            }
            
        } else {
            $this->data['cronjob_status'] = 'Disabled';
        }



        $this->data['cron_folder']	    = $cronFolder;

		$this->response->setOutput($this->load->view($this->modulePath.'/test_cron', $this->data));
    }
	
	// Remove all records
	public function removeallrecords() {
		if (isset($this->request->post['remove']) && ($this->request->post['remove']==true) && isset($this->request->post['store']) ) {
            $run_query = $this->db->query("DELETE FROM `" . DB_PREFIX . "".$this->moduleName."` WHERE `store_id`='".$this->request->post['store']."'");
            if ($run_query) echo "Success!";
		}
	}
	
	// Remove all expired coupons
	public function removeallexpiredcoupons() {
		$date_end = date('Y-m-d', time() - 60 * 60 * 24);
		if (isset($this->request->post['remove']) && ($this->request->post['remove']==true)) {
			$run_query = $this->db->query("DELETE FROM `" . DB_PREFIX . "coupon` WHERE `name` LIKE '%AbCart [%' AND `date_end`<='".$date_end."'");
			if ($run_query) echo "Success!";
		}
	}
	
	// Remove all empty records
	public function removeallemptyrecords() {
		if (isset($this->request->post['remove']) && ($this->request->post['remove']==true) && isset($this->request->post['store']) ) {
				$run_query = $this->db->query("DELETE FROM `" . DB_PREFIX . "".$this->moduleName."` WHERE `store_id`='".$this->request->post['store']."' AND `customer_info` NOT LIKE '%email\":\"%@%\"%'");
				if ($run_query) echo "Success!";
		}
	}
	
	// Remove single record
	public function removeabandonedcart() {
		if (isset($this->request->post['cart_id'])) {
			$run_query = $this->db->query("DELETE FROM `" . DB_PREFIX . "".$this->moduleName."` WHERE `id`=".(int)$this->request->post['cart_id']);
			if ($run_query) echo "Success!";
		}
	}
	
	// Get current store
	private function getCurrentStore($store_id) {    
        if($store_id && $store_id != 0) {
            $store = $this->model_setting_store->getStore($store_id);
            $store['store_email'] = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `key`= 'config_email' AND `store_id`=".$this->db->escape($store_id))->row['value'];
        } else {
            $store['store_id'] = 0;
            $store['name'] = $this->config->get('config_name');
            $store['url'] = $this->getCatalogURL();
            $store['store_email'] = $this->config->get('config_email');
        }
        return $store;
    }
	
	// Show given coupons
	public function givenCoupons() {		
		$this->listCoupons('givenCoupons');	
	}
	
	// Show used coupons
	public function usedCoupons() {		
		$this->listCoupons('usedCoupons');	
	}
	
	// Function to list the coupon codes
	private function listCoupons($action) { 
		if (isset($this->request->get['sort'])) {
				$sort = $this->request->get['sort'];
		} else {
				$sort = 'name';
		}
		if (isset($this->request->get['order'])) {
				$order = $this->request->get['order'];
		} else {
				$order = 'ASC';
		}
		if (isset($this->request->get['page'])) {
				$page = $this->request->get['page'];
		} else {
				$page = 1;
		}

		$url = '';
		if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
		}
		if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
		}
		if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
		}

		$this->data['givenCoupons'] = array();

		$dataInfo = array(
				'sort' => $sort,
				'order' => $order,
				'start' => ($page - 1) * 15,
				'limit' => 15
		);
		
		if($action == 'usedCoupons') {
			$coupon_total = $this->moduleModel->getTotalUsedCoupons(); 
			$coupons  = $this->moduleModel->getUsedCoupons($dataInfo);
		
		} else { 
			$coupon_total  = $this->moduleModel->getTotalGivenCoupons();
			$coupons  =      $this->moduleModel->getGivenCoupons($dataInfo);
		}
        
		if(!empty($coupons)) {
			foreach ($coupons as $coupon) {
				$this->data['coupons'][] = array(
					'coupon_id' => $coupon['coupon_id'],
					'name' => $coupon['name'],
					'code' => $coupon['code'],
					'discount' => $coupon['discount'],
					'date_start' => date($this->language->get('date_format_short'), strtotime($coupon['date_start'])),
					'date_end' => date($this->language->get('date_format_short'), strtotime($coupon['date_end'])),
					'status' => ($coupon['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled')),
					'date_added' => $coupon['date_added']
				);
			}
		}

		if (isset($this->error['warning'])) {
            $this->data['error_warning'] = $this->error['warning'];
		} else {
            $this->data['error_warning'] = '';
		}
        
		if (isset($this->session->data['success'])) {
            $this->data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
		} else {
            $this->data['success'] = '';
		}

		$url = '';
		if ($order == 'ASC') {
            $url .= '&order=DESC';
		} else {
            $url .= '&order=ASC';
		}
        
		if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
		}
		
		$this->data['sort_name']       = 'index.php?route=' . $this->modulePath . '/' . $action . '&' . $this->data['token_addon'] . '&sort=name' . $url;
		$this->data['sort_code']       = 'index.php?route=' . $this->modulePath . '/' . $action . '&' . $this->data['token_addon'] . '&sort=code' . $url;
		$this->data['sort_discount']   = 'index.php?route=' . $this->modulePath . '/' . $action . '&' . $this->data['token_addon'] . '&sort=discount' . $url;
		$this->data['sort_date_start'] = 'index.php?route=' . $this->modulePath . '/' . $action . '&' . $this->data['token_addon'] . '&sort=date_start' . $url;
		$this->data['sort_date_end']   = 'index.php?route=' . $this->modulePath . '/' . $action . '&' . $this->data['token_addon'] . '&sort=date_end' . $url;
		$this->data['sort_status']     = 'index.php?route=' . $this->modulePath . '/' . $action . '&' . $this->data['token_addon'] . '&sort=status' . $url;
		$this->data['sort_email']      = 'index.php?route=' . $this->modulePath . '/' . $action . '&' . $this->data['token_addon'] . '&sort=email' . $url;
		$this->data['sort_discount_type'] = 'index.php?route=' . $this->modulePath . '/' . $action . '&' . $this->data['token_addon'] . '&sort=discount_type' . $url;
		$this->data['sort_date_added']   = 'index.php?route=' . $this->modulePath . '/' . $action . '&' . $this->data['token_addon'] . '&sort=date_added' . $url;
		
        $url = '';
		
        if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
		}
		if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
		}
		
		$this->data['total']        = $coupon_total;
		$this->data['limit']        = 15;
		$pagination					= new Pagination();
		$pagination->total			= $this->data['total'];
		$pagination->page			= $page;
		$pagination->limit			= $this->data['limit'];
		$pagination->text			= $this->language->get('text_pagination');
		$pagination->url			= 'index.php?route=' . $this->modulePath . '/' . $action . '&' . $this->data['token_addon'] . $url . '&page={page}';
		$this->data['pagination']   = $pagination->render();
		$this->data['sort']         = $sort;
		$this->data['order']        = $order;
		
		$this->data['results'] = sprintf($this->language->get('text_pagination'), ($this->data['total']) ? (($page - 1) * $this->data['limit']) + 1 : 0, ((($page - 1) * $this->data['limit']) > ($this->data['total'] - $this->data['limit'])) ? $this->data['total'] : ((($page - 1) * $this->data['limit']) + $this->data['limit']), $this->data['total'], ceil($this->data['total'] / $this->data['limit']));
	
		$this->response->setOutput($this->load->view($this->modulePath.'/coupon', $this->data));
	}
	
}
