<?php
class ControllerExtensionModuleAbandonedcartsEventHandlers extends Controller {
    private $moduleName;
    private $modulePath;
    private $eventsPath;
    private $moduleModel;
    private $callModel;

    public static $customerLoggedInState;
	
    public function __construct($registry) {
        parent::__construct($registry);
        
        // Config Loader
        $this->config->load('isenselabs/abandonedcarts');
        
        /* Fill Main Variables - Begin */
        $this->moduleName           = $this->config->get('abandonedcarts_name');
        $this->callModel            = $this->config->get('abandonedcarts_model');
        $this->modulePath           = $this->config->get('abandonedcarts_path');
        $this->eventsPath           = $this->config->get('abandonedcarts_events_path');
        /* Fill Main Variables - End */
    }

    private function initModels() {
        // Load Model
        $this->load->model($this->modulePath);
        
        // Model Instance
        $this->moduleModel          = $this->{$this->callModel};

        // Settings
        $this->load->model('setting/setting');
        
        // Language
        $this->language->load($this->modulePath);
    }

    private function getRestoreId() {
        if (!empty($this->session->data['abandonedCart_ID'])) {
            $id = $this->session->data['abandonedCart_ID'];
        } else if ($this->customer->isLogged()) {
            $id = $this->customer->getEmail();
        } else if (!empty($this->session->data["guest"]) && !empty($this->session->data["guest"]["email"])) {
            $id = $this->session->data["guest"]["email"];
        } else {
            $id = $this->session->getId();
            $this->session->data['abandonedCart_ID'] = $id;
        }

        if (!$this->customer->isLogged() && ( empty($this->session->data["guest"]) || empty($this->session->data["guest"]["email"]) ) && $id != $this->session->getId()) {// Handle customer email info unset
            $id = $this->session->getId();
            $this->session->data['abandonedCart_ID'] = $id;
        }

        return $id;
    }

    public function init() {
        $this->event->unregister("*/before", $this->eventsPath . "/init");

        $this->initModels();

        $abandonedCartsSettings = $this->model_setting_setting->getSetting($this->moduleName, $this->config->get('config_store_id'));

        if (isset($abandonedCartsSettings['abandonedcarts']['Enabled']) && $abandonedCartsSettings['abandonedcarts']['Enabled']=='yes') { 
            $route = !empty($this->request->get['route']) ? $this->request->get['route'] : $this->config->get('action_default');
            if ($route == "journal2/checkout/confirm") {
                $this->event->register("controller/$route/before", new Action($this->eventsPath . "/processAbandonedCarts"));
            } else {
                $this->event->register("controller/$route/after", new Action($this->eventsPath . "/processAbandonedCarts"));
            }
            self::$customerLoggedInState = $this->customer->isLogged();
        }
    }

    public function processAbandonedCarts() {
        if (isset($this->request->get['route'])) {
            if (preg_match("@/payment/.*?/confirm@", $this->request->get["route"])) return;

            if ($this->request->get['route'] != 'journal2/checkout/confirm') {
                if (!self::$customerLoggedInState && $this->customer->isLogged()) return;// We do not want to execute the function body in this case, because the cart->getProducts() method will return an empty array, which will delete the abandonedcarts entry, but the cart may not actually be empty.
            }
        }

        $ip = (!empty($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : '*HiddenIP*';
        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
            $ip = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }

        $id = $this->getRestoreId();
        $id_escaped = $this->db->escape($id);

        $exists = $this->db->query("SELECT * FROM `" . DB_PREFIX . "abandonedcarts` WHERE `restore_id` = '$id_escaped' AND `ordered`=0");

        $customer_email = $this->customer->isLogged() ? $this->customer->getEmail() : ( (!empty($this->session->data["guest"]) && !empty($this->session->data["guest"]["email"])) ? $this->session->data["guest"]["email"] : NULL);
        if ($customer_email && $id != $customer_email) {// Handle customer email change
            $this->session->data['abandonedCart_ID'] = $customer_email;
            if ($exists->num_rows) {
                $inner_exists = $this->db->query("SELECT * FROM `" . DB_PREFIX . "abandonedcarts` WHERE `restore_id` = '" . $this->db->escape($customer_email) . "' AND `ordered`=0");
                if (!$inner_exists->num_rows) {
                    $this->db->query("UPDATE `" . DB_PREFIX . "abandonedcarts` SET `restore_id`='" . $this->db->escape($customer_email) . "' WHERE `restore_id`='$id_escaped' AND `ordered`=0");
                } else {
                    $this->db->query("DELETE FROM `" . DB_PREFIX . "abandonedcarts` WHERE `restore_id`='$id_escaped' AND `ordered`=0");
                    $exists = $inner_exists;
                }
            }
            $id = $this->customer->getEmail();
        }

        $cart = $this->cart->getProducts();
        $store_id = (int)$this->config->get('config_store_id');
        $cart = (!empty($cart)) ? $cart : '';


        $lastpage = $_SERVER["REQUEST_URI"];

        if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' && !empty($this->request->server["HTTP_REFERER"])) {
            $url_parts = parse_url($this->request->server["HTTP_REFERER"]);
            $lastpage = $url_parts["path"];
            if (!empty($url_parts["query"])) {
                $lastpage .= "?" . $url_parts["query"];
            }
        }

        $checker = $this->customer->getId();
        if (!empty($checker)) {
            $customer = array(
                'id'        => $this->customer->getId(), 
                'email'     => $this->customer->getEmail(),		
                'telephone' => $this->customer->getTelephone(),
                'firstname' => $this->customer->getFirstName(),
                'lastname'  => $this->customer->getLastName(),
                'language'  => $this->session->data['language']
            );
        } else if (!empty($this->session->data["guest"])) {
            $customer = array(
                'email'     => $this->session->data['guest']['email'],		
                'telephone' => $this->session->data['guest']['telephone'],
                'firstname' => $this->session->data['guest']['firstname'],
                'lastname'  => $this->session->data['guest']['lastname'],
                'language'  => $this->session->data['language']
            );
        } else if (!empty($this->request->post["email"]) && $this->request->get['route'] == 'journal2/checkout/confirm') {
            $customer = array(
                'email'     => $this->request->post['email'],		
                'telephone' => $this->request->post['telephone'],
                'firstname' => $this->request->post['firstname'],
                'lastname'  => $this->request->post['lastname'],
                'language'  => $this->session->data['language']
            );
        }

        if (empty($exists->row)) {
            if (!empty($cart)) {
                if (!isset($customer)) {
                    $customer = array(
                        'language' => $this->session->data['language']
                    );
                }
                $cart = json_encode($cart);
                $customer = (!empty($customer)) ? json_encode($customer) : '';
                $this->db->query("INSERT INTO `" . DB_PREFIX . "abandonedcarts` SET `cart`='".$this->db->escape($cart)."', `customer_info`='".$this->db->escape($customer)."', `last_page`='$lastpage', `ip`='$ip', `date_created`=NOW(), `date_modified`=NOW(), `restore_id`='".$id."', `store_id`='".$store_id."'");
            } 
        } else {
            if (!empty($cart)) {
                $cart = json_encode($cart);

                if (isset($customer)) {
                    $customer = json_encode($customer);
                    $this->db->query("UPDATE `" . DB_PREFIX . "abandonedcarts` SET `cart` = '".$this->db->escape($cart)."', `customer_info` = '".$this->db->escape($customer)."', `last_page`='".$this->db->escape($lastpage)."', `date_modified`=NOW() WHERE `restore_id`='$id' AND `ordered`=0");
                } else {
                    $this->db->query("UPDATE `" . DB_PREFIX . "abandonedcarts` SET `cart` = '".$this->db->escape($cart)."', `last_page`='".$this->db->escape($lastpage)."', `date_modified`=NOW() WHERE `restore_id`='$id' AND `ordered`=0");
                }
            } else {
                $this->db->query("DELETE FROM `" . DB_PREFIX . "abandonedcarts` WHERE `restore_id` = '$id' AND `ordered`=0");
                $this->session->data['abandonedCart_ID']=''; 
                unset($this->session->data['abandonedCart_ID']);
            }
        }
    }

    public function onBeforePaymentConfirm() {
        if (empty($this->session->data["order_id"])) return;

        $order_id = $this->session->data["order_id"];

        $this->load->model('setting/setting');
        $abandonedCartsSettings = $this->model_setting_setting->getSetting($this->moduleName, $this->config->get('config_store_id'));
        if (isset($abandonedCartsSettings['abandonedcarts']['Enabled']) && $abandonedCartsSettings['abandonedcarts']['Enabled']=='yes') {
            $id = $this->getRestoreId();

            $exists = $this->db->query("SELECT * FROM `" . DB_PREFIX . "abandonedcarts` WHERE `restore_id` = '$id' AND `ordered`=0");
            if (!empty($exists->rows)) {
                foreach ($exists->rows as $row) {
                    if ($row['notified']!=0) {
                        $this->db->query("UPDATE `" . DB_PREFIX . "abandonedcarts` SET `ordered` = 1 WHERE `id` = '" . $row["id"] . "'");
                    } else if ($row['notified']==0) {
                        $this->db->query("DELETE FROM `" . DB_PREFIX . "abandonedcarts` WHERE `id` = '" . $row["id"] . "' AND `ordered`=0");
                    }
                }

                $this->session->data['abandonedCart_ID']='';
                unset($this->session->data['abandonedCart_ID']);
            }
        }
    }
}
