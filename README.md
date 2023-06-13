# Laravel Boxberry SDK

### Установка
```
composer require alexwebprog/laravel-boxberry
```

### Настройка в Laravel

1. Выполните команду `php artisan vendor:publish --tag="boxberry-config"` чтобы создать файл конфигурации

        // config/boxberry.php

        // API URI
        'api_uri' => env('BOXBERRY_API_URI', 'https://api.boxberry.ru/json.php'),

        // Список токенов (если аккаунтов в Boxberry несколько), по-умолчанию используется main
        'tokens' => [
            'main' => env('BOXBERRY_TOKEN', ''),
            'another' => env('BOXBERRY_ANOTHER_TOKEN', ''),
            // Другие токены
        ],

        // Таймаут ожидания ответа
        'timeout' => env('BOXBERRY_TIMEOUT', '30'),

2. Настройке .env файл

        BOXBERRY_API_URL=
        BOXBERRY_TOKEN=
        BOXBERRY_TIMEOUT=

### Базовое использование в Laravel
```php
<?php

namespace App\Http\Controllers;

use Alexwebprog\LaravelBoxberry\Exception\BoxBerryException;
use Alexwebprog\LaravelBoxberry\Entity\Order as BoxberryOrder;
use Alexwebprog\LaravelBoxberry\Entity\Customer as BoxberryCustomer;
use Alexwebprog\LaravelBoxberry\Entity\Place as BoxberryPlace;
use Alexwebprog\LaravelBoxberry\Entity\Item as BoxberryItem;
use Alexwebprog\LaravelBoxberry\Facades\BoxberryClient;

class TestController extends Controller
{
    // Создание отправления в ПВЗ
    public function createOrder()
    {
        try {
            $order = new BoxberryOrder();
            $order->setDeliveryDate('2023-06-11'); // Дата доставки от +1 день до +5 дней от текущий даты (только для доставки по Москве, МО и Санкт-Петербургу)
            $order->setOrderId('Тестовый заказ 001'); // ID заказа в ИМ
            $order->setValuatedAmount(5990); // Объявленная стоимость
            $order->setComment('Тестовый заказ'); // Комментарий к заказу
            $order->setVid(BoxberryOrder::PVZ); // Тип доставки (1 - ПВЗ, 2 - КД, 3 - Почта России)
            $order->setPvzCode('00552'); // Код ПВЗ
            $order->setPointOfEntry('010'); // Код пункта поступления

            $customer = new BoxberryCustomer();
            $customer->setFio('Поваров Алексей'); // ФИО получателя
            $customer->setPhone('79265656565'); // Контактный номер телефона
            $customer->setEmail('povarovalexey@gmail.com'); // E-mail для оповещений

            // Только для курьерской доставки
            // $customer->setIndex(115551); // Почтовый индекс получателя (не заполянется, если в ПВЗ)
            // $customer->setCity('Москва'); // (не заполянется, если в ПВЗ)
            // $customer->setAddress('Москва, ул. Маршала Захарова, д. 3а кв. 1'); // Адрес доставки (не заполянется, если в ПВЗ)
            // $customer->setTimeFrom('10:00'); // Время доставки от
            // $customer->setTimeTo('18:00'); // Время доставки до
            // $customer->setTimeFromSecond('10:00'); // Альтернативное время доставки от
            // $customer->setTimeToSecond('18:00'); // Альтернативное время доставки до
            // $customer->setDeliveryTime('С 10 до 19, за час позвонить'); // Время доставки текстовый формат

            $order->setCustomer($customer);

            // Создаем места в заказе
            $place = new BoxberryPlace();
            $place->setWeight(900); // Вес места в граммах
            $order->setPlaces($place);

            // Создаем товары
            $item = new BoxberryItem();
            $item->setId('123124BC'); // ID товара в БД ИМ
            $item->setName('Тестовый товар'); // Название товара
            $item->setAmount(5990); // Цена единицы товара
            $item->setQuantity(1); // Количество
            //$item->setVat(20); // Ставка НДС
            $item->setUnit('шт'); // Единица измерения
            $order->setItems($item);
            //$order->setSenderName('ООО Ромашка'); // Наименование отправителя

            $result = BoxberryClient::createOrder($order);

            dd($result);
            // При успехе будет выведена информация об отправлении
            /*
             array(
               'track'=>'DUD15224387', // Трек-номер BB
               'label'=>'URI' // Ссылка на скачивание PDF файла с этикетками
             );
             */
        }

        catch (BoxBerryException $e) {
            // Обработка ошибки вызова API BB
            // $e->getMessage(); текст ошибки
            // $e->getCode(); http код ответа сервиса BB
            // $e->getRawResponse(); // ответ сервера BB как есть (http request body)
            dd($e);
        }

        catch (\Exception $e) {
            // Обработка исключения
            dd($e);
        }
    }
    
    // Отслеживание отправления
    public function getOrderStatuses()
    {
        try {
            dump(BoxberryClient::getOrderStatuses('150501_34_03')); // По номеру заказа в интернет-магазине
            /*
             Array
             (
                 [0] => Array
                     (
                         [Date] => 2019-05-01T00:56:12
                         [Name] => Принято к доставке
                         [Comment] =>
                     )
             
                 [1] => Array
                     (
                         [Date] => 2019-05-01T00:56:13
                         [Name] => Передано на сортировку
                         [Comment] =>
                     )
             
                 [2] => Array
                     (
                         [Date] => 2019-05-03T08:43:56
                         [Name] => Передан на доставку до пункта выдачи
                         [Comment] =>
                     )
             
                 [3] => Array
                     (
                         [Date] => 2019-05-04T06:47:48
                         [Name] => Передан на доставку до пункта выдачи
                         [Comment] =>
                     )
             
                 [4] => Array
                     (
                         [Date] => 2019-05-04T11:48:01
                         [Name] => Поступило в пункт выдачи
                         [Comment] => Москва (115478, Москва г, Каширское ш, д.24, строение 7)
                     )
             
             )
            */
            
            dump(BoxberryClient::getOrderStatuses('DUD15086277', true)); // По номеру Boxberry, true - полная информация
            $bbClient->getOrderStatuses('DUD15086277', true);
            /*
             Array
             (
                 [statuses] => Array
                     (
                         [0] => Array
                             (
                                 [Date] => 30.04.2019 15:35
                                 [Name] => Загружен реестр ИМ
                                 [Comment] =>
                             )
             
                         [1] => Array
                             (
                                 [Date] => 01.05.2019 00:56
                                 [Name] => Принято к доставке
                                 [Comment] =>
                             )
             
                         [2] => Array
                             (
                                 [Date] => 01.05.2019 00:56
                                 [Name] => Передано на сортировку
                                 [Comment] =>
                             )
             
                         [3] => Array
                             (
                                 [Date] => 03.05.2019 08:43
                                 [Name] => Передан на доставку до пункта выдачи
                                 [Comment] =>
                             )
             
                         [4] => Array
                             (
                                 [Date] => 04.05.2019 06:47
                                 [Name] => Передан на доставку до пункта выдачи
                                 [Comment] =>
                             )
             
                         [5] => Array
                             (
                                 [Date] => 04.05.2019 11:48
                                 [Name] => Поступило в пункт выдачи
                                 [Comment] => Москва (115478, Москва г, Каширское ш, д.24, строение 7)
                             )
             
                     )
             
                 [PD] =>
                 [sum] => 5843
                 [Weight] => 1.58
                 [PaymentMethod] => Касса
             )
             */
        }
        
        catch (BoxBerryException $e) {
            // Обработка ошибки вызова API BB
            // $e->getMessage(); текст ошибки 
            // $e->getCode(); http код ответа сервиса BB
            // $e->getRawResponse(); // ответ сервера BB как есть (http request body)
        }
        
        catch (\Exception $e) {
            // Обработка исключения
        }
        
    }

}
```

### Расширенное использование в Laravel

- Пакет сделан как обёрка для Laravel пакета https://github.com/iamwildtuna/boxberry-sdk
- Все методы исходного пакета поддерживаются
- см. Alexwebprog\LaravelBoxberry\Facades\BoxberryClient и namespace Alexwebprog\LaravelBoxberry\Client
