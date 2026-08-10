/**
 * @file
 * Fullscreen webform builder dialog for node forms.
 */

(($, Drupal, once) => {
  function open(button) {
    const url = button.dataset.editorUrl;
    if (!url) {
      return;
    }

    const title = button.dataset.dialogTitle || Drupal.t('Edit webform');
    const iframe = document.createElement('iframe');
    iframe.className = 'webform-inline-editor__iframe';
    iframe.src = url;
    iframe.title = title;

    const wrapper = document.createElement('div');
    wrapper.appendChild(iframe);

    Drupal.dialog(wrapper, {
      title,
      width: '100%',
      modal: true,
      autoResize: false,
      draggable: false,
      resizable: false,
      classes: {
        'ui-dialog': 'webform-ui-dialog webform-inline-editor-dialog',
      },
      close(event) {
        Drupal.dialog(event.target).close();
        Drupal.detachBehaviors(event.target, null, 'unload');
        $(event.target).remove();
      },
    }).showModal();
  }

  Drupal.behaviors.webformInlineEditor = {
    attach(context) {
      once(
        'webform-inline-editor-open',
        '.js-webform-inline-editor-open',
        context,
      ).forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          open(button);
        });
      });
    },
  };
})(jQuery, Drupal, once);
