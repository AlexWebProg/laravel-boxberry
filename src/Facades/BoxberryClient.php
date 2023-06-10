<?php
namespace Alexwebprog\LaravelBoxberry\Facades;

use Alexwebprog\LaravelBoxberry\Client;
use Alexwebprog\LaravelBoxberry\Entity\CalculateParams;
use Alexwebprog\LaravelBoxberry\Entity\Intake;
use Alexwebprog\LaravelBoxberry\Entity\Order;
use Illuminate\Support\Facades\Facade;

/**
 * @method static getPvzList(bool $prepaid = false, bool $short = false, int|null $city_code = null) : array
 * @method static getCityList(bool $all = false) : array
 * @method static getZipList() : array
 * @method static checkZip(int $index) : array
 * @method static getOrderStatuses(string $order_id, bool $all = false) : array
 * @method static getOrderServices(string $order_id) : bool|array
 * @method static getCourierCities() : array
 * @method static getPointsForParcels() : array
 * @method static pointDetails(int $point_id, bool $photo = false) : array
 * @method static calcTariff(CalculateParams $calcParams) : TariffInfo
 * @method static getLabel(string $track) : array
 * @method static getLabelFile(string $track) : ResponseInterface|bool
 * @method static getAllOrdersInfo($order_ids, string $parcel_type = Client::PARCEL_ORDER_ID)
 * @method static getFileByLink($link) : \Psr\Http\Message\ResponseInterface
 * @method static getOrderInfoByTrack($track_id) : array
 * @method static getOrderInfoByOrderId($order_id) : array
 * @method static getOrdersInProgress() : array
 * @method static getOrderList(string $from = null, string $to = null) : array
 * @method static createIntake(Intake $intake) : int
 * @method static getOrdersNotAct(bool $arr = false) : array|string
 * @method static deleteOrderByOrderId(string $order_id, int $cancelType = 2) : bool
 * @method static deleteOrderByTrack($track, int $cancelType = 2) : bool
 * @method static createOrder(Order $order) : array
 * @method static createOrdersTransferAct($track_nums) : array
 * @method static getActsList(string $from = null, string $to = null) : array
 * @method static parcelFiles($typeFile = '', $params = []) : mixed
 * @method static getParcelFileActToId($parcelId) : mixed
 * @method static getParcelFileActTMCToId($parcelId) : mixed
 * @method static getParcelFileBarcodesToId($parcelId) : mixed
 *
 * @see \Alexwebprog\LaravelBoxberry\Client
 */
class BoxberryClient extends Facade
{

    protected static function getFacadeAccessor() : string
    {
        return Client::class;
    }
}
