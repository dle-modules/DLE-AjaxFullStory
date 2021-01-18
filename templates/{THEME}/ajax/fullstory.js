$(document).on('click', '[data-afs-id]', function (event) {
  event.preventDefault();
  var $this = $(event.currentTarget);
  var $data = $this.data();

  $.magnificPopup.open({
    type: 'ajax',
    mainClass: 'afs-modal',
    items: {
      // eslint-disable-next-line camelcase
      src: dle_root + 'engine/ajax/controller.php'
    },
    ajax: {
      settings: {
        type: 'GET',
        dataType: 'html',
        data: {
          mod: 'fullstory',
          newsId: $data.afsId,
          preset: $data.afsPreset ? $data.afsPreset : '',
          template: $data.afsTemplate ? $data.afsTemplate : ''
        }
      }
    }
  });
});