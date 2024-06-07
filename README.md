# Тестовое задание "Микро-CRM для торговли"

## Общая информация

Сайт сделан на следующем технологическом стеке:

- Laravel 11
- MySQL

## Инициализация

1. Убедитесь, что в системе установлен Docker и Docker Compose.

2. Скопируйте файлы `.env.example` в `.env` в корне репозитория и в папке `src`.

3. Настройте подключение к базе данных — задайте имя пользователя БД и пароль в _обоих_ `.env`-файлах.

4. В терминале зайдите в корневую папку репозитория и запустите контейнеры:

    ```bash
    docker compose up -d
    ```

5. Зайдите в PHP-контейнер (не контейнер сервера и не контейнер PhpMyAdmin) и установите PHP-зависимости:

    ```bash
    docker exec -it ИМЯ_ИЛИ_ID_КОНТЕЙНЕРА bash
    composer install # или composer update
    ```

6. В этом же контейнере сгенерируйте ключ Laravel-приложения:

    ```bash
    php artisan key:generate
    ```

7. Выполните миграции базы данных:

    ```bash
    php artisan migrate
    ```

## Генерация тестовых данных

Для генерации тестовых данных (товаров, складов и остатков на них) выполните следующую команду в PHP-контейнере:

```bash
php artisan generate:mock-data
```

## API-методы

### Просмотреть список складов

#### URL

```
GET api/warehouses
```

#### Параметры

- `page` — номер страницы. По умолчанию 1.
- `per_page` — количество записей на страницу. По умолчанию 15.

#### Структура ответа

```json
[
    {
        "id": integer,
        "name": string
    },
    // ...
]
```

### Просмотреть список товаров с их остатками по складам

#### URL

```
GET api/product-stocks
```

#### Параметры

- `page` — номер страницы. По умолчанию 1.
- `per_page` — количество записей на страницу. По умолчанию 15.

#### Структура ответа

```json
[
    {
        "id": integer,
        "name": string,
        "price": float,
        "stocks": [
            {
                "warehouse": {
                    "id": integer,
                    "name": string
                },
                "stock": integer
            }
        ]
    },
    // ...
]
```

### Получить список заказов

#### URL

```
GET api/orders
```

#### Параметры

- `page` — номер страницы. По умолчанию 1.
- `per_page` — количество записей на страницу. По умолчанию 15.
- `filter[customer]` — имя или часть имени покупателя.
- `filter[status]` — статус заказа ("active", "completed" или "cancelled")
- `filter[warehouse]` — ID склада.

#### Структура ответа

```json
[
    {
        "id": integer,
        "customer": string,
        "created_at": datetime(string),
        "completed_at": datetime(string)|null,
        "warehouse": {
            "id": integer,
            "name": string
        },
        "status": string,
        "items": [
            {
                "id": integer,
                "product": {
                    "id": integer,
                    "name": string,
                    "price": float
                },
                "count": integer
            }
            // ...
        ]
    },
    // ...
]
```

### Создать заказ

#### URL

```
POST api/orders
```

#### Структура тела запроса

```json
{
    "customer": string,
    "warehouse_id": integer,
    "products": [
        {
            "id": integer, // ID товара
            "count": integer
        },
        // ...
    ]
}
```

#### Структура ответа

В ответ API возвращает объект заказа.

```json
{
    "id": integer,
    "customer": string,
    "created_at": datetime(string),
    "completed_at": datetime(string)|null,
    "warehouse": {
        "id": integer,
        "name": string
    },
    "status": string,
    "items": [
        {
            "id": integer,
            "product": {
                "id": integer,
                "name": string,
                "price": float
            },
            "count": integer
        }
    ]
}
```

#### Структура ошибки валидации полей
```json
{
    field: [
        string,
        string,
        // ...
    ],
    // ...
}
```

#### Структура ошибки (не хватает товаров на складе)
```json
{
    "error": string // Сообщение об ошибке
}
```

### Обновить заказ

#### URL

```
PUT api/orders/{id}
```

`{id}` — ID заказа.

#### Структура тела запроса

```json
{
    "customer": string,
    "products": [
        {
            "id": integer, // ID товара
            "count": integer
        },
        // ...
    ]
}
```

#### Структура ответа

В ответ API возвращает объект заказа.

```json
{
    "id": integer,
    "customer": string,
    "created_at": datetime(string),
    "completed_at": datetime(string)|null,
    "warehouse": {
        "id": integer,
        "name": string
    },
    "status": string,
    "items": [
        {
            "id": integer,
            "product": {
                "id": integer,
                "name": string,
                "price": float
            },
            "count": integer
        }
    ]
}
```

#### Структура ошибки валидации полей
```json
{
    field: [
        string,
        string,
        // ...
    ],
    // ...
}
```

#### Структура ошибки (не хватает товаров на складе)
```json
{
    "error": string // Сообщение об ошибке
}
```

### Завершить заказ

#### URL

```
PUT api/orders/{id}/complete
```

`{id}` — ID заказа.

#### Структура ответа

В ответ API возвращает объект заказа.

```json
{
    "id": integer,
    "customer": string,
    "created_at": datetime(string),
    "completed_at": datetime(string)|null,
    "warehouse": {
        "id": integer,
        "name": string
    },
    "status": string,
    "items": [
        {
            "id": integer,
            "product": {
                "id": integer,
                "name": string,
                "price": float
            },
            "count": integer
        }
    ]
}
```

#### Структура ошибки
```json
{
    "error": string // Сообщение об ошибке
}
```

### Отменить заказ

#### URL

```
PUT api/orders/{id}/cancel
```

`{id}` — ID заказа.

#### Структура ответа

В ответ API возвращает объект заказа.

```json
{
    "id": integer,
    "customer": string,
    "created_at": datetime(string),
    "completed_at": datetime(string)|null,
    "warehouse": {
        "id": integer,
        "name": string
    },
    "status": string,
    "items": [
        {
            "id": integer,
            "product": {
                "id": integer,
                "name": string,
                "price": float
            },
            "count": integer
        }
    ]
}
```

### Возобновить заказ (перевод из отмены в работу)

#### URL

```
PUT api/orders/{id}/restore
```

`{id}` — ID заказа.

#### Структура ответа

В ответ API возвращает объект заказа.

```json
{
    "id": integer,
    "customer": string,
    "created_at": datetime(string),
    "completed_at": datetime(string)|null,
    "warehouse": {
        "id": integer,
        "name": string
    },
    "status": string,
    "items": [
        {
            "id": integer,
            "product": {
                "id": integer,
                "name": string,
                "price": float
            },
            "count": integer
        }
    ]
}
```

#### Структура ошибки
```json
{
    "error": string // Сообщение об ошибке
}
```

### Просмотреть историю изменения остатков товаров

#### URL

```
GET api/stock-history
```

#### Параметры

- `page` — номер страницы. По умолчанию 1.
- `per_page` — количество записей на страницу. По умолчанию 15.
- `filter[product]` — ID товара.
- `filter[warehouse]` — ID склада.
- `filter[date]` — Показать за конкретную дату. Формат — `YYYY-MM-DD`.
- `filter[date_since]` — От фильтровать после даты. Формат — `YYYY-MM-DD`.
- `filter[date_until]` — От фильтровать по дату включительно. Формат — `YYYY-MM-DD`.

#### Структура ответа

```json
[
    {
        "id": integer,
        "warehouse": {
            "id": integer,
            "name": string
        },
        "product": {
            "id": integer,
            "name": string,
            "price": float
        },
        "stock": integer,
        "date": datetime(string)
    },
    // ...
]
```
