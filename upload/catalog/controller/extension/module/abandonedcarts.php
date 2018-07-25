<?php
class ControllerExtensionModuleAbandonedcarts extends Controller
{
    private $moduleName;
    private $modulePath;
    private $moduleModel;
    private $callModel;
	
    public function __construct($registry) {
        parent::__construct($registry);
        
        // Config Loader
        $this->config->load('isenselabs/abandonedcarts');
        
        /* Fill Main Variables - Begin */
        $this->moduleName           = $this->config->get('abandonedcarts_name');
        $this->callModel            = $this->config->get('abandonedcarts_model');
        $this->modulePath           = $this->config->get('abandonedcarts_path');
        /* Fill Main Variables - End */
        
        // Load Model
        $this->load->model($this->modulePath);
        
        // Model Instance
        $this->moduleModel          = $this->{$this->callModel};

        // Settings
        $this->load->model('setting/setting');
        
        // Language
        $this->language->load($this->modulePath);
    }
    
    public function restoreCart(){
        if (isset($_GET['id']) && !empty($_GET['id'])) {
			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "".$this->moduleName."` WHERE `id`=".(int)$this->db->escape($_GET['id'])." LIMIT 1");
            
            if ($query->num_rows) {
                $result = $query->row;
                $result['cart']			= json_decode($result['cart'], true);
                
                if (!$this->customer->isLogged()) {
                    $this->session->data["abandonedCart_ID"] = $result["restore_id"];
                    if (!empty($result["customer_info"])) {
                        $customer_info = json_decode($result["customer_info"], true);
                        if ($customer_info) {
                            if (!empty($customer_info["id"])) {
                                $this->db->query("DELETE FROM " . DB_PREFIX . "cart WHERE customer_id = '" . $this->db->escape($customer_info["id"]) . "'");
                            }

                            if (!empty($customer_info["email"])) {
                                $this->session->data["guest"]["email"] = $customer_info["email"];
                            }

                            if (!empty($customer_info["telephone"])) {
                                $this->session->data["guest"]["telephone"] = $customer_info["telephone"];
                            }

                            if (!empty($customer_info["firstname"])) {
                                $this->session->data["guest"]["firstname"] = $customer_info["firstname"];
                            }

                            if (!empty($customer_info["lastname"])) {
                                $this->session->data["guest"]["lastname"] = $customer_info["lastname"];
                            }
                        }
                    }
                } else {
                    $this->session->data["abandonedCart_ID"] = $this->customer->getEmail();
                    $this->db->query("UPDATE `" . DB_PREFIX . "".$this->moduleName."` SET restore_id='" . $this->db->escape($this->customer->getEmail()) . "' WHERE `id`='" . (int)$this->db->escape($_GET['id']) . "'");
                }
                
                if ($this->cart->hasProducts()) {
                    $this->cart->clear();
                }
                
                foreach ($result['cart'] as $product) {
                    if (count($product['option'])>0) {
                       $options = array();

                    	foreach ($product['option'] as $key => $product_option) {
                    		if ($product_option['type'] == 'select' || $product_option['type'] == 'radio') {
                    			$options[$product_option['product_option_id']] = $product_option['product_option_value_id'];
                    		} else if($product_option['type'] == 'text' || $product_option['type'] == 'textarea' || $product_option['type'] == 'date' || $product_option['type'] == 'file' || $product_option['type'] == 'datetime' || $product_option['type'] == 'time') {
                    			$options[$product_option['product_option_id']] = $product_option['value'];
                    		} else if ($product_option['type'] == 'checkbox'){
                    			$options[$product_option['product_option_id']] = array($product_option['product_option_value_id']);
                    		}
                    	}
                    	
                        $this->cart->add($product['product_id'], $product['quantity'], $options, $product['recurring']);
                    } else {
                        $this->cart->add($product['product_id'], $product['quantity'], array(), $product['recurring']);
                    }
                }
                $this->response->redirect($this->url->link('checkout/cart', '', 'SSL')); 
            } else {
               $this->response->redirect($this->url->link('common/home', '', 'SSL')); 
            }
        } else {
			$this->response->redirect($this->url->link('common/home', '', 'SSL'));
		}
    }
    
    public function sendReminder()  {
		$log = new Log($this->moduleName."_log.txt");
		$log->write("Executing cron job functionality");

        $this->load->model('setting/setting');
        $this->load->model('tool/image');
		$this->load->model('catalog/product');
		
        $this->language->load('product/product');
		
		$data['text_price']     = $this->language->get('text_price');
		$data['text_qty']       = $this->language->get('text_qty');

		$stores = array_merge(array(0 => $this->moduleModel->getStore(0)), $this->moduleModel->getStores());
		foreach ($stores as $store) {
			$setting = $this->model_setting_setting->getSetting($this->moduleName, $store['store_id']);
			$moduleData = isset($setting[$this->moduleName]) ? $setting[$this->moduleName] : array();
			if (!empty($moduleData['Enabled']) && $moduleData['Enabled'] == 'yes' && isset($moduleData['ScheduleEnabled']) && $moduleData['ScheduleEnabled']='yes' && isset($moduleData['MailTemplate'])) {
                $usedEmails = array();
				
                $store_data = $this->moduleModel->getStore($store['store_id']);
                
                foreach ($moduleData['MailTemplate'] as $mailtemplate) {
					if ($mailtemplate['Enabled']=='yes') {
						$results = $this->moduleModel->getCarts($mailtemplate['Delay'], $store['store_id']);

						foreach ($results as $result) {
							$result['customer_info'] = json_decode($result['customer_info'], true);
							if (isset($result['customer_info']['email']) && isset($result['customer_info']['firstname']) && isset($result['customer_info']['lastname'])) {
								
								// If invalid email, delete record and continue with the execution
								if (!filter_var($result['customer_info']['email'], FILTER_VALIDATE_EMAIL)) {
									$run_query = $this->db->query("DELETE FROM `" . DB_PREFIX . "abandonedcarts` WHERE `id`=".(int)$result['id']);
									continue;
								}
								
								if (!in_array($result['customer_info']['email'], $usedEmails,true)) {
									// Subject and message language
									if (isset($result['customer_info']['language'])) {
										$language_id = $this->moduleModel->getLanguageId($result['customer_info']['language']);
										$Subject = $mailtemplate['Subject'][$language_id];
										$Message = html_entity_decode($mailtemplate['Message'][$language_id]);
									} else {
										$languages = $this->getLanguages();
										$firstLanguage = array_shift($languages);
										$firstLanguageCode = $firstLanguage['language_id'];
										$Subject = $mailtemplate['Subject'][$firstLanguageCode];
										$Message = html_entity_decode($mailtemplate['Message'][$firstLanguageCode]);						
									}
						
									$result['cart']			= json_decode($result['cart'], true);
										
									$this->load->model('localisation/language');
									$OrderLanguage = $this->model_localisation_language->getLanguage($language_id);
									$LangVars =  $this->moduleModel->loadLanguage($OrderLanguage['code'],$this->modulePath);
									
									$catalog_link			= "";
									$store_data				= $this->moduleModel->getStore($store['store_id']);
									$catalog_link			= $store_data['url'];
									$width					= (isset($mailtemplate['ProductWidth'])) ? $mailtemplate['ProductWidth'] : '60';
									$height					= (isset($mailtemplate['ProductWidth'])) ? $mailtemplate['ProductHeight'] : '60';
                                    $skipOutOfStock			= (!empty($mailtemplate['SkipOutOfStock']) && $mailtemplate['SkipOutOfStock'] == 'yes');

                                    $availableProductsCount = 0;
						
									$CartProducts = '<table width="100%">';
									$CartProducts .= '<thead>
														<tr class="table-header">
														<td class="left" width="70%"><strong>'.$LangVars['text_product'].'</strong></td>
								  						<td class="left" width="15%"><strong>'.$LangVars['text_qty'].'</strong></td>
								  						<td class="left" width="15%"><strong>'.$LangVars['text_price'].'</strong></td>
														</tr>
													 </thead>';

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
                    $CartProducts .='<td class="name"><div id="picture" style="float:left;padding-right:3px;"><a href="'.$catalog_link.'index.php?route=product/product&product_id='. $product['product_id'].'" target="_blank"><img src="'.$image_thumb.'" /></a></div> <a href="'.$catalog_link.'index.php?route=product/product&product_id='. $product['product_id'].'" target="_blank">'.$product['name'].'</a><br />';

										foreach ($product['option'] as $option) {
											   $CartProducts .= '- <small>'.$option['name'].' '.$option['value'].'</small><br />';
										}
										//
										if ($moduleData['Taxes']=='yes') {
											$price = $this->tax->calculate($product['price'], $product_info['tax_class_id'], $this->config->get('config_tax'));
										} else {
											$price = $product['price'];
										}
										//
										$CartProducts .= '</td>
												  <td class="quantity">x&nbsp;'.$product['quantity'].'</td>
												  <td class="price">'.($this->currency->format($price, $this->config->get('config_currency'))).'</td>
												</tr>';
									}
									$CartProducts .='</table>';

                                    if (!$availableProductsCount && $skipOutOfStock) {
                                        continue;
                                    }
						
						
									if ($mailtemplate['DiscountType']=='N') {
										// do nothing here
									} else {
										if ($mailtemplate['DiscountApply']=='all_products') {
											$DiscountCode			= $this->moduleModel->generateuniquerandomcouponcode();
											$TimeEnd				=  time() + $mailtemplate['DiscountValidity'] * 24 * 60 * 60;
											$CouponData				= array('name' => 'AbCart [' . $result['customer_info']['email'].']',
											'code'					=> $DiscountCode, 
											'discount'				=> $mailtemplate['Discount'],
											'type'					=> $mailtemplate['DiscountType'],
											'total'		   			=> $mailtemplate['TotalAmount'],
											'logged'			    => $mailtemplate['DiscountCustomerLogin'],
					                        'shipping'			    => $mailtemplate['DiscountShipping'],
											'date_start'	  		=> date('Y-m-d', time()),
											'date_end'				=> date('Y-m-d', $TimeEnd),
											'uses_total'	  		=> '1',
											'uses_customer'   		=> '1',
											'status'		  		=> '1');
											$this->moduleModel->addCoupon($CouponData);
										} else if ($mailtemplate['DiscountApply']=='cart_products') {
											$cart_products			= array();
											foreach ($result['cart'] as $product) { 
												$cart_products[] 	= $product['product_id'];
											}
											$DiscountCode	 		= $this->moduleModel->generateuniquerandomcouponcode();
											$TimeEnd		  		= time() + $mailtemplate['DiscountValidity'] * 24 * 60 * 60;
											$CouponData	   			= array('name' => 'AbCart [' . $result['customer_info']['email'].']',
											'code'					=> $DiscountCode, 
											'discount'				=> $mailtemplate['Discount'],
											'type'					=> $mailtemplate['DiscountType'],
											'total'		   			=> $mailtemplate['TotalAmount'],
											'logged'			    => $mailtemplate['DiscountCustomerLogin'],
					                        'shipping'			    => $mailtemplate['DiscountShipping'],
											'coupon_product'  		=> $cart_products,
											'date_start'	 		=> date('Y-m-d', time()),
											'date_end'				=> date('Y-m-d', $TimeEnd),
											'uses_total'	 		=> '1',
											'uses_customer'  		=> '1',
											'status'		  		=> '1');
											$this->moduleModel->addCoupon($CouponData);
										}
									}
						
									$patterns = array();
									$patterns[0] = '{firstname}';
									$patterns[1] = '{lastname}';
									$patterns[2] = '{cart_content}';
									if (!($mailtemplate['DiscountType']=='N')) {
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
									if (!($mailtemplate['DiscountType']=='N')) {
										$replacements[3] = $DiscountCode;
										$replacements[4] = $mailtemplate['Discount'];
										$replacements[5] = $mailtemplate['TotalAmount'];
										$replacements[6] = date($moduleData['DateFormat'], $TimeEnd);
									}
									$replacements[7] = '<a href="'.$catalog_link.'index.php?route=' . $this->modulePath . '/removeCart&id='.$result['id'].'">'.$LangVars['text_unsubscribe'].'</a>';
                                    $replacements[8] = '<a href="'.$catalog_link.'index.php?route=' . $this->modulePath . '/restoreCart&id='.$result['id'].'">'.$LangVars['text_restorecart'].'</a>';
									$HTMLMail = str_replace($patterns, $replacements, $Message);
                                    
                                    $patterns_subject = array();
                                    $patterns_subject[0] = '{firstname}';
                                    $patterns_subject[1] = '{lastname}';
                                    $replacements = array();
                                    $replacements_subject[0] = $result['customer_info']['firstname'];
                                    $replacements_subject[1] = $result['customer_info']['lastname'];
                                    $HTMLSubject =  str_replace($patterns_subject, $replacements_subject, $Subject);
                                    
									$MailData = array(
										'email' =>  $result['customer_info']['email'],
										'message' => $HTMLMail, 
										'subject' => $HTMLSubject,
										'store_id' => $store['store_id'],
                                        'store_name' => $store_data['name'],
                                        'store_email' => $store_data['store_email']
                                    );
									
									if (!in_array($result['customer_info']['email'], $usedEmails,true)) {	
										$emailResult = $this->moduleModel->sendMail($MailData);
										$usedEmails[] = $result['customer_info']['email'];
									}
									$run_query = $this->db->query("UPDATE `" . DB_PREFIX . "".$this->moduleName."` SET notified = (notified + 1) WHERE `id`=".(int)$result['id']);

									if (isset($mailtemplate['RemoveAfterSend']))
										$run_query = $this->db->query("DELETE FROM `" . DB_PREFIX . "".$this->moduleName."` WHERE `id`=".(int)$result['id']);
								}
							} else {
								if (isset($mailtemplate['RemoveEmptyRecords']) && $mailtemplate['RemoveEmptyRecords']=='yes') {
									$run_query = $this->db->query("DELETE FROM `" . DB_PREFIX . "abandonedcarts` WHERE `id`=".(int)$result['id']);
								}
							}
			
						}
                    }					
				}
                
                $counter = count($usedEmails);
                $result = "Cron was executed successfully! A total of <strong>".$counter."</strong> emails were sent to the customers from the module AbandonedCarts.<br />";
                
                if (isset($moduleData['NotifyEmail']) && $moduleData['NotifyEmail']=='yes') {
                    $MailData = array(
                        'email' =>  $this->config->get('config_email'),
                        'message' => $result, 
                        'subject' => 'AbandonedCarts Cron Task',
                        'store_id' => $store['store_id'],
                        'store_name' => $store['name'],
                        'store_email' => $this->config->get('config_email')
                    );
                    $this->moduleModel->sendMail($MailData);
				} else {
					echo $result; //exit;
				}	
                
			}				
		}
    }
	
	private function getLanguages($data = array()) {
		if ($data) {
			$sql = "SELECT * FROM " . DB_PREFIX . "language";

			$sort_data = array(
				'name',
				'code',
				'sort_order'
			);	

			if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
				$sql .= " ORDER BY " . $data['sort'];	
			} else {
				$sql .= " ORDER BY sort_order, name";	
			}

			if (isset($data['order']) && ($data['order'] == 'DESC')) {
				$sql .= " DESC";
			} else {
				$sql .= " ASC";
			}

			if (isset($data['start']) || isset($data['limit'])) {
				if ($data['start'] < 0) {
					$data['start'] = 0;
				}					

				if ($data['limit'] < 1) {
					$data['limit'] = 20;
				}	

				$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
			}

			$query = $this->db->query($sql);

			return $query->rows;
		} else {
			$language_data = $this->cache->get('language');

			if (!$language_data) {
				$language_data = array();

				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "language ORDER BY sort_order, name");

				foreach ($query->rows as $result) {
					$language_data[$result['code']] = array(
						'language_id' => $result['language_id'],
						'name'        => $result['name'],
						'code'        => $result['code'],
						'locale'      => $result['locale'],
						'image'       => $result['image'],
						'directory'   => $result['directory'],
						'filename'    => $result['filename'],
						'sort_order'  => $result['sort_order'],
						'status'      => $result['status']
					);
				}

				$this->cache->set('language', $language_data);
			}

			return $language_data;			
		}
	}
	
	public function removeCart(){
        if (isset($_GET['id']) && !empty($_GET['id'])) {
			$run_query = $this->db->query("DELETE FROM `" . DB_PREFIX . "".$this->moduleName."` WHERE `id`=".(int)$this->db->escape($_GET['id']));
			echo '<center>'.$this->language->get('unsubscribe_success').'</center>';
        } else {
			echo '<center>'.$this->language->get('unsubscribe_error').'</center>';			
		}
    }
}
