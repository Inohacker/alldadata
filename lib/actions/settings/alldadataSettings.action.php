<?php

class alldadataSettingsAction extends waViewAction
{
    public function execute() {

        if(!$this->getRights('settings')) {
            throw new waRightsException('Доступ ограничен');
        }

        $this->setLayout(new alldadataBackendLayout());

        $app_settings = new waAppSettingsModel();

        if (waRequest::post()) {
            $app_settings->set('alldadata', 'token', waRequest::post('token', '', waRequest::TYPE_STRING_TRIM));
            $app_settings->set('alldadata', 'secret', waRequest::post('secret', '', waRequest::TYPE_STRING_TRIM));
        }

        $token = $app_settings->get('alldadata', 'token');
        $secret = $app_settings->get('alldadata', 'secret');

        $this->view->assign('token', $token);
        $this->view->assign('secret', $secret);

        $count = 'Необходимо указать API ключ и Secret';

        if(!empty($token) && !empty($secret)) {

            $options = [
                'format'        => waNet::FORMAT_JSON,
                'timeout'       => 10,
            ];

            $headers = [
                'Authorization' => 'Token ' . $token,
                'X-Secret' => $secret,
            ];

            $net = new waNet($options, $headers);
            $net->userAgent('BNP Dadata App Bot v.1.0');

            try {
                $response = $net->query('https://dadata.ru/api/v2/stat/daily', waNet::METHOD_GET);

                if(isset($response['services']['suggestions'])) {
                    $count = 'Подсказки <b>' . $response['services']['suggestions'] . '</b>';
                }

                if(isset($response['services']['clean'])) {
                    $count .= ' Стандартизация <b>' . $response['services']['clean'] . '</b>';
                }

            } catch (waException $e) {
                $count = $e->getMessage();
            }
        } elseif (empty($token)) {
            $count = 'Необходимо указать API ключ';
        } elseif (empty($secret)) {
            $count = 'Необходимо указать Secret';
        }

        $this->view->assign('count', $count);

        $balance = 'Необходимо указать API ключ и Secret';

        if(!empty($token) && !empty($secret)) {

            $options = [
                'format'        => waNet::FORMAT_JSON,
                'timeout'       => 10,
            ];

            $headers = [
                'Authorization' => 'Token ' . $token,
                'X-Secret' => $secret,
            ];

            $net = new waNet($options, $headers);
            $net->userAgent('BNP Dadata App Bot v.1.0');

            try {
                $response = $net->query('https://dadata.ru/api/v2/profile/balance', waNet::METHOD_GET);

                if(isset($response['balance'])) {
                    $balance = $response['balance'];
                }

            } catch (waException $e) {
                $balance = $e->getMessage();
            }
        } elseif (empty($token)) {
            $balance = 'Необходимо указать API ключ';
        } elseif (empty($secret)) {
            $balance = 'Необходимо указать Secret';
        }

        $this->view->assign('balance', $balance);
    }
}