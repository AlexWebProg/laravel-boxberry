<?php

namespace Alexwebprog\LaravelBoxberry;

use Alexwebprog\LaravelBoxberry\Entity\CalculateParams;
use Alexwebprog\LaravelBoxberry\Entity\Intake;
use Alexwebprog\LaravelBoxberry\Entity\Order;
use Alexwebprog\LaravelBoxberry\Entity\TariffInfo;
use Alexwebprog\LaravelBoxberry\Exception\BoxBerryException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class Client implements LoggerAwareInterface
{
    const PARCEL_TRACK = 'track';
    const PARCEL_ORDER_ID = 'order_id';

    use LoggerAwareTrait;

    /** @var array */
    private $tokenList = [];
    /** @var null */
    private $currentToken = null;
    /** @var \GuzzleHttp\Client|null */
    private $httpClient = null;

    /**
     * Client constructor.
     * @param array $config - массив конфигурации BoxBerry
     */
    public function __construct(array $config)
    {
        $this->httpClient = new \GuzzleHttp\Client([
            'base_uri' => $config['api_uri'],
            'timeout' => $config['timeout']
        ]);
        $this->setToken('main', $config['tokens']['main']); // Заносим токен BB и присваиваем ему ключ main
        $this->setCurrentToken('main'); // Указываем клиенту использовать ключ main для запросов
    }

    /**
     * Возвращает токен из хранилища по ключу
     *
     * @param string $key - Ключ токена
     * @return string|false
     */
    public function getToken(string $key) : string|false
    {
        return !empty($this->tokenList[$key]) ? $this->tokenList[$key] : false;
    }

    /**
     * Заносит токен в хранилище
     *
     * @param string $key - Ключ токена
     * @param string $token - Токен доступа к API
     */
    public function setToken(string $key, string $token) : void
    {
        $this->tokenList[$key] = $token;
        $this->setCurrentToken($key);
    }

    /**
     * Задает токен, который будет использован клиентом для запросов к API
     *
     * @param string $key - Ключ токена
     * @throws \InvalidArgumentException
     */
    public function setCurrentToken(string $key) : void
    {
        $this->currentToken = $this->getToken($key);
        if (empty($this->currentToken))
            throw new \InvalidArgumentException('Не выбран API-токен!');
    }

    public function getCurrentToken()
    {
        if (empty($this->currentToken))
            throw new \InvalidArgumentException('Не выбран API-токен!');

        return $this->currentToken;
    }

    /**
     * Инициализирует вызов к API
     *
     * @param $type
     * @param $method
     * @param array $params
     * @return array
     * @throws BoxBerryException|GuzzleException
     */
    private function callApi($type, $method, array $params = []) : array
    {
        if ($type == 'POST') {
            $data = $params;
            $params = [];
            if ($method == 'ParcelInfo') {
                $params = $data;
            }else {
                $params['sdata'] = json_encode($data);
            }
            unset($data);
        }

        $params['token'] = $this->getCurrentToken();
        $params['method'] = $method;

        switch ($type) {
            case 'GET':
                if ($this->logger) {
                    $this->logger->info("BoxBerry {$type} API request {$method}: " . http_build_query($params));
                }
                $response = $this->httpClient->get('', ['query' => $params]);
                break;
            case 'POST':
                if ($this->logger) {
                    $this->logger->info("BoxBerry API {$type} request {$method}: " . json_encode($params));
                }
                $response = $this->httpClient->post('', ['form_params' => $params]);
                break;
        }

        $request = http_build_query($params);

        $json = $response->getBody()->getContents();

        if ($this->logger) {
            $headers = $response->getHeaders();
            $headers['http_status'] = $response->getStatusCode();
            $this->logger->info("BoxBerry API response {$method}: " . $json, $headers);
        }

        if ($response->getStatusCode() != 200)
            throw new BoxBerryException('Неверный код ответа от сервера BoxBerry при вызове метода ' . $method . ': ' . $response->getStatusCode(), $response->getStatusCode(), $json, $request);

        $respBB = json_decode($json, true);

        if (empty($respBB))
            throw new BoxBerryException('От сервера BoxBerry при вызове метода ' . $method . ' пришел пустой ответ', $response->getStatusCode(), $json, $request);

        if (!empty($respBB['err']))
            throw new BoxBerryException('От сервера BoxBerry при вызове метода ' . $method . ' получена ошибка: ' . $respBB['err'], $response->getStatusCode(), $json, $request);


        if (!empty($respBB[0]['err']))
            throw new BoxBerryException('От сервера BoxBerry при вызове метода ' . $method . ' получена ошибка: ' . $respBB[0]['err'], $response->getStatusCode(), $json, $request);

        return $respBB;
    }

    /**
     * Возврат списка ПВЗ
     *
     * @param boolean $prepaid true - все ПВЗ, false - с возможностью оплаты при получении
     * @param boolean $short - true - краткая информация о ПВЗ с датой последнего изменения
     * @param int $city_code - код города BB, если нужны ПВЗ в заданном городе
     * @return array
     * @throws BoxBerryException|GuzzleException
     */
    public function getPvzList(bool $prepaid = false, bool $short = false, int|null $city_code = null) : array
    {
        $method = 'ListPoints';
        $params = [];

        if ($short)
            $method .= 'Short';

        if ($prepaid)
            $params['prepaid'] = 1;

        if ($city_code)
            $params['CityCode'] = $city_code;

        return $this->callApi('GET', $method, $params);
    }

    /**
     * Возвращает список городов, в которых есть пункты выдачи заказов
     *
     * @param boolean $all - true - список городов, в которых осуществляется доставка + в которых есть ПВЗ
     * @return array
     * @throws BoxBerryException|GuzzleException
     */
    public function getCityList(bool $all = false) : array
    {
        $method = 'ListCities';
        if ($all)
            $method .= 'Full';

        return $this->callApi('GET', $method);
    }

    /**
     * Возвращает список почтовых индексов, для которых возможна курьерская доставка
     *
     * @return array
     * @throws BoxBerryException|GuzzleException
     */
    public function getZipList() : array
    {
        return $this->callApi('GET', 'ListZips');
    }

    /**
     * Проверка возможности КД в заданном индексе
     *
     * @param int $index - Почтовый индекс получателя
     * @return array
     * @throws BoxBerryException|GuzzleException
     */
    public function checkZip(int $index) : array
    {
        $response = $this->callApi('GET', 'ZipCheck', ['Zip' => $index]);
        return $response[0];
    }

    /**
     * Информация о статусах заказа
     *
     * @param string $order_id - ID заказа магазина или трекномер BB
     * @param bool $all true - полная информация, false - краткая информация
     * @return array
     * @throws BoxBerryException|GuzzleException
     */
    public function getOrderStatuses(string $order_id, bool $all = false) : array
    {
        $method = 'ListStatuses';
        if ($all)
            $method .= 'Full';

        return $this->callApi('GET', $method, ['ImId' => $order_id]);
    }

    /**
     * Информация о статусах массива заказов
     *
     * @param array $order_ids - массив ID заказов магазина или трекномеров BB
     * @return array
     * @throws BoxBerryException|GuzzleException
     */
    public function getMultipleOrdersStatuses(array $order_ids) : array
    {
        $method = 'GetLastStatusData';
        return $this->callApi('GET', $method, ['trackNumbers' => $order_ids]);
    }

    /**
     * Информация об услугах по отправлению
     *
     * @param string $order_id - ID заказа магазина или трекномер BB
     * @return bool|array
     * @throws BoxBerryException|GuzzleException
     */
    public function getOrderServices(string $order_id) : bool|array
    {
        $response = $this->callApi('GET', 'ListServices', ['ImId' => $order_id]);
        if (empty($response) || empty($response[0]['Sum'])) {
            return false;
        } else {
            return $response;
        }
    }

    /**
     * Список городов, в которых осуществляется курьерская доставка
     *
     * @return array
     * @throws BoxBerryException|GuzzleException
     */
    public function getCourierCities() : array
    {
        return $this->callApi('GET', 'CourierListCities');
    }

    /**
     * Список точек приема посылок
     *
     * @return array
     * @throws BoxBerryException|GuzzleException
     */
    public function getPointsForParcels() : array
    {
        return $this->callApi('GET', 'PointsForParcels');
    }


    /**
     * Информация о ПВЗ
     *
     * @param int $point_id
     * @param bool $photo
     * @return array
     * @throws BoxBerryException|GuzzleException
     */
    public function pointDetails(int $point_id, bool $photo = false) : array
    {
        if ($photo) $photo = 1; else $photo = 0;

        return $this->callApi('GET', 'PointsDescription', ['code' => $point_id, 'photo' => $photo]);
    }

    /**
     * Расчета тарифа на доставку
     *
     * @param CalculateParams $calcParams
     * @return TariffInfo
     * @throws BoxBerryException|GuzzleException
     */
    public function calcTariff(CalculateParams $calcParams) : TariffInfo
    {
        $params = $calcParams->asArr();
        $response = $this->callApi('GET', 'DeliveryCosts', $params);
        return new TariffInfo($response);
    }

    /**
     * Этикетка по заказу
     *
     * @param string $track - трекномер BB
     * @return array
     * @throws BoxBerryException|GuzzleException
     */
    public function getLabel(string $track) : array
    {
        return $this->callApi('GET', 'ParselCheck', ['ImId' => $track]);
    }

    /**
     * Получить файл этикетки
     *
     * @param string $track - трекномер BB
     * @return ResponseInterface|bool
     * @throws BoxBerryException|GuzzleException
     */
    public function getLabelFile(string $track) : ResponseInterface|bool
    {
        $response = $this->getLabel($track);
        if ($response) {
            return $this->getFileByLink($response['label']);
        }
        return false;
    }

    /**
     * @param $order_ids - список заказов
     *
     * @param string $parcel_type - тип выборки (трек номер посылки или номер заказа магазина)
     * @return array|void
     * @throws BoxBerryException|GuzzleException
     */
    public function getAllOrdersInfo($order_ids, string $parcel_type = Client::PARCEL_ORDER_ID)
    {
        if (!in_array($parcel_type, $this->getParcelsType())) {
            $parcel_type = self::PARCEL_ORDER_ID;
        }
        $parcels = array_map(function ($order) use ($parcel_type) {
            return [$parcel_type => trim($order)];
        }, $order_ids);
        if (!$parcels) {
            return;
        }
        return $this->callApi('POST', 'ParcelInfo', ['parcels' => $parcels]);
    }

    /**
     * Универсальный метод получения файлов по ссылке
     *
     * @param $link - ссылка на файл
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function getFileByLink($link) : ResponseInterface
    {
        return $this->httpClient->request('GET', $link);
    }

    /**
     * Полная информация о заказе по трек номеру
     *
     * @param $track_id - трекномер BB
     * @return array
     * @throws BoxBerryException|GuzzleException
     *
     */
    public function getOrderInfoByTrack($track_id) : array
    {
        return $this->callApi('POST', 'ParcelInfo', ['parcels' => [['track' => $track_id]]]);
    }

    /**
     * Полная информация о заказе по ID заказа в магазине
     *
     * @param $order_id - ID заказа магазина
     * @return array
     * @throws BoxBerryException|GuzzleException
     */
    public function getOrderInfoByOrderId($order_id) : array
    {
        return $this->callApi('POST', 'ParcelInfo', ['parcels' => [['order_id' => $order_id]]]);
    }

    /**
     * Получает информацию по заказам, которые фактически переданы на доставку в BoxBerry, но они еще не доставлены получателю
     *
     * @return array
     * @throws BoxBerryException|GuzzleException
     */
    public function getOrdersInProgress() : array
    {
        return $this->callApi('GET', 'OrdersBalance');
    }

    /**
     * Позволяет получить список созданных через API посылок
     * Если не указывать диапазоны дат, то будет возвращен последний созданный заказ
     *
     * @param string|null $from - период от (дата в любом формате)
     * @param string|null $to - период до (дата в любом формате)
     * @return array
     * @throws BoxBerryException|GuzzleException
     */
    public function getOrderList(string $from = null, string $to = null) : array
    {
        $params = [];

        if ($from)
            $params['from'] = date('Ymd', strtotime($from));

        if ($to)
            $params['to'] = date('Ymd', strtotime($to));

        return $this->callApi('GET', 'ParselStory', $params);
    }

    /**
     * Создание заявки на забор
     *
     * @param Intake $intake - заявка на забор
     * @return int - номер созданной заявки на забор
     * @throws BoxBerryException|GuzzleException
     */
    public function createIntake(Intake $intake) : int
    {
        $params = $intake->asArr();
        $response = $this->callApi('GET', 'CreateIntake', $params);
        if (empty($response['message']))
            throw new BoxBerryException('От сервера BoxBerry не пришел номер заявки!');

        return $response['message'];
    }


    /**
     * Позволяет получить список всех трекинг кодов посылок которые есть в кабинете но не были сформированы в акт
     *
     * @param bool $arr - вернуть список в виде массива
     * @return array|string
     * @throws BoxBerryException|GuzzleException
     */
    public function getOrdersNotAct(bool $arr = false) : array|string
    {
        $response = $this->callApi('GET', 'ParselList');
        if ($arr)
            return explode(',', $response['ImIds']);
        else
            return $response['ImIds'];
    }


    /**
     * Позволяет удалить заказ по ID заказа магазина
     *
     * @param string $order_id - ID заказа магазина
     * @param int $cancelType - вариант отмены заказа (1 - удалить посылку, 2 - отозвать посылку)
     * @return bool
     * @throws BoxBerryException|GuzzleException
     */
    public function deleteOrderByOrderId(string $order_id, int $cancelType = 2) : bool
    {
        $response = $this->callApi('GET', 'CancelOrder', ['orderid' => $order_id, 'cancelType' => $cancelType]);
        if (!empty($response['err']) && $response['err'] === false) {
            return true;
        }

        return false;
    }

    /**
     * Позволяет удалить заказ по трекномеру BB
     *
     * @param $track - трекномер BB
     * @param int $cancelType
     * @return bool
     * @throws BoxBerryException|GuzzleException
     */
    public function deleteOrderByTrack($track, int $cancelType = 2) : bool
    {
        $response = $this->callApi('GET', 'CancelOrder', ['track' => $track, 'cancelType' => $cancelType]);
        if (!empty($response['err']) && $response['err'] === false) {
            return true;
        }

        return false;
    }

    /**
     * Создание заказа
     *
     * @param Order $order - Параметры заказа
     * @return array
     * @throws BoxBerryException|GuzzleException
     */
    public function createOrder(Order $order) : array
    {
        $params = $order->asArr();
        return $this->callApi('POST', 'ParselCreate', $params);
    }


    /**
     * Создание акта передачи посылок в BB.
     * Внимание! сервис работает только с посылками созданными через API ЛК.
     *
     * @param $track_nums
     * @return array
     * @throws BoxBerryException|GuzzleException
     */
    public function createOrdersTransferAct($track_nums) : array
    {
        if (empty($track_nums) || !is_array($track_nums))
            throw new \InvalidArgumentException('Не передан массив трек-номеров заказов!');

        return $this->callApi('GET', 'ParselSend', ['ImIds' => implode(',', $track_nums)]);
    }

    /**
     * Позволяет получить список созданных через API актов передачи заказов
     * Если не указывать диапазоны дат, то будет возвращен последний созданный акт
     *
     * @param string $from - период от (дата в любом формате)
     * @param string $to - период до (дата в любом формате)
     * @return array
     * @throws BoxBerryException|GuzzleException
     */
    public function getActsList(string $from = null, string $to = null) : array
    {
        $params = [];

        if ($from)
            $params['from'] = date('Ymd', strtotime($from));

        if ($to)
            $params['to'] = date('Ymd', strtotime($to));

        return $this->callApi('GET', 'ParselSendStory', $params);
    }

    /**
     * @param string $typeFile - тип файла, принимает значения 'act' - Акт приема передачи посылки, 'barcodes' - печатная форма этикеток
     * @param array $params - параметры
     * @return mixed
     * @throws GuzzleException
     */
    private function parcelFiles(string $typeFile = '', array $params = []) : mixed
    {
        $uri = 'https://api.boxberry.ru/parcel-files/' . $typeFile;
        $this->httpClient = new \GuzzleHttp\Client(['base_uri' => $uri, 'timeout' => 300]);
        $params['token'] = $this->getCurrentToken();

        return $this->httpClient->get('', ['query' => $params]);
    }

    /**
     * Позволяет получить файл "Акта приема передачи посылки (АПП)" по номеру АПП
     *
     * @param $parcelId - номер акта приема передачи посылки
     * @return mixed
     * @throws GuzzleException
     */
    public function getParcelFileActToId($parcelId) : mixed
    {
        return $this->parcelFiles('act', ['upload_id' => $parcelId]);
    }

    /**
     * Позволяет получить файл акта ТМЦ (если подключена услуга в ЛК) по номеру АПП
     *
     * @param $parcelId - номер акта приема передачи посылки
     * @return mixed
     * @throws GuzzleException
     */
    public function getParcelFileActTMCToId($parcelId) : mixed
    {
        return $this->parcelFiles('act', ['upload_id' => $parcelId, 'type_act' => 'tmc']);
    }

    /**
     * Позволяет получить печатную форму этикеток по номеру АПП
     *
     * @param $parcelId - номер акта приема передачи посылки
     * @return mixed
     * @throws GuzzleException
     */
    public function getParcelFileBarcodesToId($parcelId) : mixed
    {
        return $this->parcelFiles('barcodes', ['upload_id' => $parcelId]);
    }

    private function getParcelsType() : array
    {
        return [self::PARCEL_TRACK, self::PARCEL_ORDER_ID];
    }
}
