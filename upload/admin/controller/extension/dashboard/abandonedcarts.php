<?php
class ControllerExtensionDashboardAbandonedCarts extends Controller {
    private $error = array();

	public function index() {
		$this->load->language('extension/dashboard/abandonedcarts');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
        
        // Token
        $this->config->load('isenselabs/abandonedcarts');
        $data['token_string'] = $this->config->get('abandonedcarts_token_string');
        $data['token']        = $this->session->data[$data['token_string']];
        $data['token_addon']  = $data['token_string'] . '=' . $data['token'];

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('dashboard_abandonedcarts', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', $data['token_addon'] . '&type=dashboard', true));
		}

		$data['heading_title'] = $this->language->get('heading_title');
		
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');

		$data['entry_width'] = $this->language->get('entry_width');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', $data['token_addon'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', $data['token_addon'] . '&type=dashboard', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/dashboard/abandonedcarts', $data['token_addon'], true)
		);

		$data['action'] = $this->url->link('extension/dashboard/abandonedcarts', $data['token_addon'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', $data['token_addon'] . '&type=dashboard', true);

		if (isset($this->request->post['dashboard_abandonedcarts_width'])) {
			$data['dashboard_abandonedcarts_width'] = $this->request->post['dashboard_abandonedcarts_width'];
		} else {
			$data['dashboard_abandonedcarts_width'] = $this->config->get('dashboard_abandonedcarts_width');
		}
		
		$data['columns'] = array();
		
		for ($i = 3; $i <= 12; $i++) {
			$data['columns'][] = $i;
		}
				
		if (isset($this->request->post['dashboard_abandonedcarts_status'])) {
			$data['dashboard_abandonedcarts_status'] = $this->request->post['dashboard_abandonedcarts_status'];
		} else {
			$data['dashboard_abandonedcarts_status'] = $this->config->get('dashboard_abandonedcarts_status');
		}

		if (isset($this->request->post['dashboard_abandonedcarts_sort_order'])) {
			$data['dashboard_abandonedcarts_sort_order'] = $this->request->post['dashboard_abandonedcarts_sort_order'];
		} else {
			$data['dashboard_abandonedcarts_sort_order'] = $this->config->get('dashboard_abandonedcarts_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/dashboard/abandonedcarts_form', $data));
	}
    
    protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/dashboard/abandonedcarts')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
    
	public function dashboard() {
		$this->load->language('extension/dashboard/abandonedcarts');

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_view'] = $this->language->get('text_view');

        // Token
        $this->config->load('isenselabs/abandonedcarts');
        $data['token_string'] = $this->config->get('abandonedcarts_token_string');
        $data['token']        = $this->session->data[$data['token_string']];
        $data['token_addon']  = $data['token_string'] . '=' . $data['token'];

        $data['total'] = 0;

        $AB_count = $this->db->query("SELECT count(*) as total FROM `" . DB_PREFIX . "abandonedcarts` WHERE `notified`=0");
    
        $data['total'] = $AB_count->row['total'];

		$data['link'] = $this->url->link('extension/module/abandonedcarts', $data['token_addon'], true);

		return $this->load->view('extension/dashboard/abandonedcarts_info', $data);
	}
    
    public function install() {
        return true;   
    }
}
