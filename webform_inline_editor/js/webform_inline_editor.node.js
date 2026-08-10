/**
 * @file
 * Fullscreen webform builder dialog for node forms.
 */

(($, Drupal, once) => {
  const DIALOG_CLASS = 'webform-inline-editor-dialog';

  function fit(dialog) {
    if (!dialog?.classList.contains(DIALOG_CLASS)) {
      return;
    }

    Object.assign(dialog.style, {
      width: '100vw',
      height: '100vh',
      maxWidth: '100vw',
      maxHeight: '100vh',
      top: '0',
      left: '0',
      margin: '0',
    });

    const titleHeight = dialog.querySelector('.ui-dialog-titlebar')?.offsetHeight || 0;
    const content = dialog.querySelector('.ui-dialog-content');
    if (content) {
      Object.assign(content.style, {
        width: '100%',
        height: `calc(100vh - ${titleHeight}px)`,
        maxHeight: 'none',
        padding: '0',
        overflow: 'hidden',
      });
    }

    const iframe = dialog.querySelector('.webform-inline-editor__iframe');
    if (iframe) {
      Object.assign(iframe.style, {
        width: '100%',
        height: '100%',
        border: '0',
        display: 'block',
      });
    }
  }

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
        'ui-dialog': `webform-ui-dialog ${DIALOG_CLASS}`,
      },
      // jQuery's remove() destroys the widget, which unwraps the .ui-dialog shell.
      close(event) {
        Drupal.dialog(event.target).close();
        Drupal.detachBehaviors(event.target, null, 'unload');
        $(event.target).remove();
      },
    }).showModal();

    requestAnimationFrame(() => {
      document.querySelectorAll(`.ui-dialog.${DIALOG_CLASS}`).forEach(fit);
    });
  }

  Drupal.behaviors.webformInlineEditor = {
    attach(context) {
      once('webform-inline-editor-open', '.js-webform-inline-editor-open', context).forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          open(button);
        });
      });

      once('webform-inline-editor-fit', 'body', context).forEach(() => {
        window.addEventListener('dialog:aftercreate', (event) => {
          fit(event.target instanceof Element ? event.target.closest('.ui-dialog') : null);
        });
        window.addEventListener('resize', () => {
          document.querySelectorAll(`.ui-dialog.${DIALOG_CLASS}`).forEach(fit);
        });
      });
    },
  };
})(jQuery, Drupal, once);
