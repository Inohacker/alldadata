<?php

require_once __DIR__ . '/../vendors/autoload.php';
use Jaybizzle\CrawlerDetect\CrawlerDetect;

class alldadataApi
{

    const SUGGESTION_API_URL = 'https://suggestions.dadata.ru/suggestions/api/';
    const SUGGESTION_API_VERSION = '4_1';
    const SUGGESTION_URL = self::SUGGESTION_API_URL . self::SUGGESTION_API_VERSION;

    const SUGGESTION_API_METHODS = [
        'geolocation'           => '/rs/detectAddressByIp',
        'kladr_fias_address'    => '/rs/findById/address',
        'fio'                   => '/rs/suggest/fio',
        'address'               => '/rs/suggest/address',
        'organizations_suggest' => '/rs/suggest/party',
        'organizations_find'    => '/rs/findById/party',
        'email'                 => '/rs/suggest/email',
        'bank'                  => '/rs/suggest/bank',
        'post_suggest'          => '/rs/suggest/postal_office',
        'post_find'             => '/rs/findById/postal_office',
        'address_geolocate'     => '/rs/geolocate/address',
        'delivery_find_by_id'   => '/rs/findById/delivery',
    ];

    const CLEAN_API_URL = 'https://dadata.ru/api/';
    const CLEAN_API_VERSION = 'v2';
    const CLEAN_URL = self::CLEAN_API_URL . self::CLEAN_API_VERSION . '/clean/';

    const AVAILABLE_CLEAN_FIELDS = [
        'address',
        'phone',
        'passport',
        'name',
        'email',
        'birthdate',
        'vehicle',
    ];

    const USER_AGENT = 'BNP Dadata Bot v.2.0';
    const SESSION_PREFIX = 'alldadata';

    const DEFAULT_OPTIONS = [
        'timeout' => 20,
        'format' => waNet::FORMAT_JSON,
    ];

    private $token, $secret;

    public function __construct() {
        $appSettings = new waAppSettingsModel();
        $this->token = $appSettings->get('alldadata', 'token');
        $this->secret = $appSettings->get('alldadata', 'secret');
    }

    /**
     * Проверка на бота
     * @param null $userAgent
     * @return mixed
     */
    public function isBot($userAgent = null) {
        $crawlerDetect = new CrawlerDetect;
        return $crawlerDetect->isCrawler($userAgent);
    }

    /**
     * Проверка наличия токена
     * @return bool
     */
    public function tokenAvailable() {
        return !empty($this->token);
    }

    /**
     * Проверка наличия Secret
     * @return bool
     */
    public function secretAvailable() {
        return !empty($this->secret);
    }

    /**
     * Идентификатор города в СДЭК
     * @see https://dadata.ru/api/delivery/
     * @param string $query ФИАС или КЛАДР код города
     * @return array
     */
    public function deliveryFindById($query) {

        if(empty($query)) {
            return ['error' => 'Не передан запрос'];
        }

        if(!$url = $this->getUrlByMethod('delivery_find_by_id')) {
            return ['error' => 'Не удалось получить url для запроса'];
        }

        $data = [
            'query' => $query,
        ];

        return $this->apiRequest($url, $data);
    }

    /**
     * Обратное геокодирование (адрес по координатам)
     * @see https://dadata.ru/api/geolocate/
     * @param string $lat
     * @param string $lon
     * @return array
     */
    public function addressGeolocate($lat = '', $lon = '') {

        if(empty($lat) || empty($lon)) {
            return ['error' => 'Координаты не заданы'];
        }

        $data = [
            'lat' => $lat,
            'lon' => $lon,
        ];

        if(!$url = $this->getUrlByMethod('address_geolocate')) {
            return ['error' => 'Не удалось получить url для запроса'];
        }

        return $this->apiRequest($url, $data);
    }

    /**
     * Геолокация по ip
     * @see https://dadata.ru/api/detect_address_by_ip/
     * @param string $ip если не указан, берется ip пользователя
     * @param bool $session
     * @param string|array $returnPath что имеено вернуть в ответе all|data|['city', 'country', etc]
     * @return array|SimpleXMLElement|string|waNet
     */
    public function geoLocation($ip = '', $session = false, $returnPath = 'all') {

        $sessionLocation = [];
        $setSession = false;

        if(empty($ip)) {
            $ip = waRequest::getIp();

            if($session) {
                $sessionLocation = $this->getSessionLocation();
            }

            $setSession = true;
        }

        if(!$url = $this->getUrlByMethod('geolocation')) {
            return ['error' => 'Не удалось получить url для запроса'];
        }

        if(empty($sessionLocation)) {
            $url .= '?ip=' . $ip;

            $response = $this->apiRequest($url, [], false, waNet::METHOD_GET);

            if(isset($response['error'])) {
                return $response;
            }

            $location = $response['location'];

            if($setSession) {
                $this->setSessionLocation($location);
            }

        } else {
            $location = $sessionLocation;
        }

        if(empty($location)) {
            return ['error' => 'Ничего не найдено'];
        }

        return $this->getReturnPath($location, $returnPath);
    }

    /**
     * Адрес по kladr_id или fias_id
     * @see https://dadata.ru/api/find-address/
     * @param string $query
     * @param string|array $returnPath что именно вернуть в ответе all|data|['city', 'country', etc]
     * @return array|SimpleXMLElement|string|waNet
     */
    public function kladrFiasAddress($query = '', $returnPath = 'all') {

        if(empty($query)) {
            return ['error' => 'Не передан запрос'];
        }

        if(!$url = $this->getUrlByMethod('kladr_fias_address')) {
            return ['error' => 'Не удалось получить url для запроса'];
        }

        $data = [
            'query' => $query,
        ];

        $response = $this->apiRequest($url, $data);

        if(isset($response['error'])) {
            return $response;
        }

        $location = $response['suggestions'][0];

        if(empty($location)) {
            return ['error' => 'Ничего не найдено'];
        }

        return $this->getReturnPath($location, $returnPath);
    }

    /**
     * Подсказки по адресу
     * @see https://confluence.hflabs.ru/pages/viewpage.action?pageId=751730844
     * @param array $params
     * @return array|SimpleXMLElement|string|waNet
     */
    public function addressSuggestions($params = []) {

        if(empty($params)) {
            return ['error' => 'Не передан запрос'];
        }

        if(!$url = $this->getUrlByMethod('address')) {
            return ['error' => 'Не удалось получить url для запроса'];
        }

        return $this->apiRequest($url, $params);
    }

    /**
     * Подсказки по ФИО
     * @see https://confluence.hflabs.ru/pages/viewpage.action?pageId=751730856
     * @param array $params
     * @return array
     */
    public function fioSuggestions($params = []) {

        if(empty($params)) {
            return ['error' => 'Не передан запрос'];
        }

        if(!$url = $this->getUrlByMethod('fio')) {
            return ['error' => 'Не удалось получить url для запроса'];
        }

        return $this->apiRequest($url, $params);
    }

    /**
     * Подсказки по организациям
     * @see https://confluence.hflabs.ru/pages/viewpage.action?pageId=751730862
     * @param array $params
     * @return array|SimpleXMLElement|string|waNet
     */
    public function organizationsSuggestions($params = []) {

        if(empty($params)) {
            return ['error' => 'Не передан запрос'];
        }

        if(!$url = $this->getUrlByMethod('organizations_suggest')) {
            return ['error' => 'Не удалось получить url для запроса'];
        }

        return $this->apiRequest($url, $params);
    }

    /**
     * Поиск организации по ИНН или ОГРН
     * @see https://dadata.ru/api/find-party/
     * @param array $params
     * @return array|SimpleXMLElement|string|waNet
     */
    public function organizationFind($params = []) {

        if(empty($params)) {
            return ['error' => 'Не передан запрос'];
        }

        if(!$url = $this->getUrlByMethod('organizations_find')) {
            return ['error' => 'Не удалось получить url для запроса'];
        }

        return $this->apiRequest($url, $params);
    }

    /**
     * Подсказки по email
     * @see https://confluence.hflabs.ru/pages/viewpage.action?pageId=751730873
     * @param array $params
     * @return array|SimpleXMLElement|string|waNet
     */
    public function emailSuggestions($params = []) {

        if(empty($params)) {
            return ['error' => 'Не передан запрос'];
        }

        if(!$url = $this->getUrlByMethod('email')) {
            return ['error' => 'Не удалось получить url для запроса'];
        }

        return $this->apiRequest($url, $params);
    }



    /**
     * Подсказки по банкам
     * @see https://confluence.hflabs.ru/pages/viewpage.action?pageId=751730879
     * @param array $params
     * @return array|SimpleXMLElement|string|waNet
     */
    public function bankSuggestions($params = []) {

        if(empty($params)) {
            return ['error' => 'Не передан запрос'];
        }

        if(!$url = $this->getUrlByMethod('bank')) {
            return ['error' => 'Не удалось получить url для запроса'];
        }

        return $this->apiRequest($url, $params);
    }

    /**
     * Поиск почтового отделения по индексу
     * @see https://dadata.ru/api/suggest/postal_office/
     * @param string $query
     * @return array|SimpleXMLElement|string|waNet
     */
    public function postFind($query = '') {
        if(empty($query)) {
            return ['error' => 'Не передан запрос'];
        }

        if(!$url = $this->getUrlByMethod('post_find')) {
            return ['error' => 'Не удалось получить url для запроса'];
        }

        $data = [
            'query' => $query
        ];

        return $this->apiRequest($url, $data);
    }

    /**
     * Стандартизация по типу
     * @see https://dadata.ru/api/clean/
     * @param string $type address|phone|passport|name|email|birthdate|vehicle
     * @param string $query
     * @return array|SimpleXMLElement|string|waNet
     */
    public function cleanByType($type = '', $query = '') {

        if(empty($type) || !in_array($type, self::AVAILABLE_CLEAN_FIELDS)) {
            return ['error' => 'Не удалось определить метод стандартизации'];
        }

        if(empty($query)) {
            return ['error' => 'Не передан запрос'];
        }

        $url = self::CLEAN_URL . $type;

        $data = [$query];

        return $this->apiRequest($url, $data, true);
    }

    /**
     * Стандартизация составной записи
     * @see https://dadata.ru/api/clean/
     * @param array $data
     * @return array|SimpleXMLElement|string|waNet
     */
    public function cleanStructure($data = []) {
        if(empty($data)) {
            return ['error' => 'Не передан запрос'];
        }

        $url = self::CLEAN_URL;

        return $this->apiRequest($url, $data, true);
    }

    private function getSessionLocation() {
        $keyName = self::SESSION_PREFIX . '/location';
        return wa()->getStorage()->get($keyName);
    }

    private function setSessionLocation($location = []) {
        $keyName = self::SESSION_PREFIX . '/location';
        wa()->getStorage()->set($keyName, $location);
    }

    /**
     * Получение части url по названию метода
     * @param string $method
     * @return string
     */
    private function getUrlByMethod($method = '') {
        if(empty($method) || empty(self::SUGGESTION_API_METHODS[$method])) {
            return '';
        }
        return self::SUGGESTION_URL . self::SUGGESTION_API_METHODS[$method];
    }

    /**
     * Парсит пришедшие данные и возвращает нужные части
     * @param $location
     * @param $returnPath
     * @return array
     */
    private function getReturnPath($location, $returnPath) {

        $response = [];

        if(is_string($returnPath)) {

            switch ($returnPath) {
                case 'all':
                    $response = $location;
                    break;

                case 'data':
                    $response = isset($location['data']) ? $location['data'] : ['error' => 'Пришел пустой ответ'];
                    break;

                default:
                    $response = ['error' => 'Не удалось определить какую часть надо вернуть'];
            }
        } elseif (is_array($returnPath)) {
            foreach ($returnPath as $field) {
                $response[$field] = $location['data'][$field];
            }
        } else {
            $response = ['error' => 'Не удалось определить какую часть надо вернуть'];
        }
        return $response;
    }

    /**
     * @param string $url
     * @param array $data
     * @param bool $secretNeeded
     * @param string $requestMethod
     * @return array|SimpleXMLElement|string|waNet
     */
    private function apiRequest($url, $data = [], $secretNeeded = false, $requestMethod = waNet::METHOD_POST) {

        $response = [];

        if(!$this->tokenAvailable()) {
            $response['error'][] = 'Не указан токен';
        }

        if(empty($url)) {
            $response['error'][] = 'Не передан url';
        }

        if($secretNeeded && !$this->secretAvailable()) {
            $response['error'][] = 'Для запроса требуется Secret, но он не указан';
        }

        if(!empty($response)) {
            return $response;
        }

        $options = self::DEFAULT_OPTIONS;

        $headers = [
            'Authorization' => 'Token ' . $this->token,
        ];

        if($secretNeeded) {
            $headers['X-Secret'] = $this->secret;
        }

        $net = new waNet($options, $headers);
        $net->userAgent(self::USER_AGENT);

        try {
            $response = $net->query($url, $data, $requestMethod);
        } catch (waException $e) {
            $response['error'] = $e->getMessage();
        }

        return $response;
    }

}
