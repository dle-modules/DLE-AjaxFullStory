# { ajax-full-story } DLE
Бесплатный модуль для загрузки полной новости в DLE средствами AJAX

## Требования
- Версия DLE: **10.2+** (на более старых не проверялся, но должен работать вплоть до 9.6)

## Особенности модуля
- Не требует каких-либо правок движка
- Учёт прав доступа к новости
- Подсчёт количества просмотров (если это разрешено)
- Корректная очистка кеша модуля
- Поддержка всех тегов
- Кеширование на стороне клиента (модуль отдаёт правильные заголовки)

## Установка
- Если сайт работает в кодировке windows-1251, необходимо перекодировать файлы модуля в эту кодировку.
- Залить содержимое папки **upload** в корень сайта.
- В нужном месте любого шаблона вставить минимальный код:
``` html
<span data-afs-id="{news-id}">Быстрый просмотр</span>
```
где `{news-id}` - ID новости (**обязательный параметр**).
- Так же можно использовать дополнительные атрибуты:
    ``` html
    <span 
        data-afs-id="{news-id}" 
        data-afs-template="mytemplate" 
        data-afs-preset="mypreset"
    >Быстрый просмотр</span>
    ```
    + `data-afs-template="mytemplate"` - Путь к шаблону модуля относительно текущей папки с шаблоном сайта. Если на сайте разрешена смена скина, то путь будет построен относительно активного в данный момент шаблона сайта. По умолчанию: **{THEME}/ajax/fullstory**. (Необязательный параметр).
    + `data-afs-preset="mypreset"` - Путь к файлу с настройками модуля. По умолчанию не используется.
    Подробнее о параметрах читайте в документации.

- В js файл шаблона вставить:
``` javascript
$(document).on('click', '[data-afs-id]', function () {
    var $this = $(this),
        $data = $this.data();

    $.ajax({
            url: dle_root + 'engine/ajax/full-story.php',
            type: 'GET',
            dataType: 'html',
            data: {
                newsId: $data.afsId, // Обязательная переменная
                preset: ($data.afsPreset) ? $data.afsPreset : '', // название файла с настройками
                template: ($data.afsTemplate) ? $data.afsTemplate : '', // Название файла с шаблоном
            },
        })
        .done(function (data) {
            var $html = $(data);

            // Данные получены, можно заняться разбором и показать их в диалоге
            // Ниже простейший пример вывода контента в стандартном модальном окне DLE

            var modalId = 'afs-' + $data.afsId + '-' + $data.afsPreset + '-' + $data.afsTemplate;
            modalId = modalId.replace(/\//g, "-");

            var $modalBlock = $('<div style="display: none;"><div id="' + modalId + '"></div></div>');

            $modalBlock
                .appendTo('body')
                .find('#' + modalId)
                .html($html)
                .dialog({
                    width: 800
                });

        })
        .fail(function () {
            console.log("full-story error");
        });
});
```

5. В CSS-файл шаблона вставить код для стилизации выводимых ошибок:
``` CSS
.afs-error {
    /*Общий стиль для всех ошибок*/
    padding: 20px;
    background: #fff;
    color: #424242;
}
.afs-news-error {
    /*Стиль ошибки, если новость не найдена*/
    background: #eceff1;
}
.afs-tpl-error {
    /*Стиль ошибки, если не найден шаблон*/
    color: #b71c1c;
}
.afs-perm-error {
    /*Стиль ошибки, если не достаточно прав для просмотра полной новости*/
    background: #e65100;
    color: #F5F5F5;
}
```

## Документация
- Документация по модулю находится на [этой странице](https://github.com/pafnuty/ajax-full-story-DLE/blob/master/DOCUMENTATION.md)

## Контакты
URL:     http://pafnuty.name/
twitter: https://twitter.com/pafnuty_name
google+: http://gplus.to/pafnuty
email:   pafnuty10@gmail.com
