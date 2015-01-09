# { ajax-full-story } DLE
Бесплатный модуль для загрузки полной новости в DLE средствами AJAX

## Требования
- Версия DLE: **10.3** (на более старых не проверялся)

## Особенности модуля
- Не требует каких-либо правок движка
- Учёт прав доступа к новости
- Подсчёт количества просмотров (если это разрешено)
- Корректная очистка кеша
- Поддержка всех тегов

## Установка
- Залить содержимое папки **upload** в корень сайта.
- В нужном месте любого шаблона вставить 
``` html
<span class="btn" data-fs-id="{news-id}">Быстрый просмотр</span>
```
где `{news-id}` -- ID новости (**обязательный параметр**)
- В js файл шаблона вставить:
``` javascript
$(document).on('click', '[data-fs-id]', function(event) {
    event.preventDefault();
    var $this = $(this),
        $data = $this.data();

    console.log($data);
    $.ajax({
        url: dle_root + 'engine/ajax/full-story.php',
        type: 'GET',
        dataType: 'html',
        data: {
            newsId: $data.fsId,
            preset: ($data.fsPreset) ? $data.fsPreset : '',
            template: ($data.fsTemplate) ? $data.fsTemplate : '',
        },
    })
    .done(function (data) {
        var $html = $(data);
        // Тут можно писать обработчик полученных данных
        console.log($html);
    })
    .fail(function() {
        console.log("full-story error");
    });
    
});
```
## Документация
- в работе.