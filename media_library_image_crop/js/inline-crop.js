/**
 * @file
 * Inline crop UI for media library field widget selections
 */

(function (Drupal, once, debounce, $) {
  'use strict';

  const selectors = {
    root: '[data-media-library-image-crop-inline]',
    previewPane: '.media-library-image-crop__preview-pane',
    cropPane: '.media-library-image-crop__crop-pane',
    previewImage: '.media-library-image-crop__preview-image',
    previewWrapper: '.media-library-image-crop__preview',
    cropTypeSelect: '.media-library-image-crop__crop-type-select',
    startCrop: '.media-library-image-crop__start-crop',
    resetCrop: '.media-library-image-crop__reset-crop',
    applyCrop: '.media-library-image-crop__apply-crop',
  };

  function cropState(container) {
    if (!container.mediaLibraryImageCrop) {
      container.mediaLibraryImageCrop = {
        session: 0,
        cropType: null,
      };
    }
    return container.mediaLibraryImageCrop;
  }

  function nextSession(container) {
    const state = cropState(container);
    state.session += 1;
    return state.session;
  }

  function isSessionActive(container, session) {
    return cropState(container).session === session;
  }

  function getCropTypeSelect(container) {
    return container.querySelector(selectors.cropTypeSelect);
  }

  function getActiveCropTypeId(container) {
    const state = cropState(container);
    if (state.cropType) {
      return state.cropType;
    }
    const select = getCropTypeSelect(container);
    return select ? select.value : null;
  }

  function setActiveCropTypeId(container, typeId) {
    if (!typeId) {
      return;
    }
    cropState(container).cropType = typeId;
    const select = getCropTypeSelect(container);
    if (select && select.value !== typeId) {
      select.value = typeId;
    }
  }

  function getCropInstance(container) {
    const wrapper = container.querySelector('[data-drupal-iwc="wrapper"]');
    if (!wrapper) {
      return null;
    }
    return $(wrapper).data('ImageWidgetCrop') || null;
  }

  function getCropTypeInstance(instance, typeId) {
    if (!instance || !instance.types) {
      return null;
    }
    return instance.types.find((type) => type.id === typeId) || null;
  }

  function openCropType(container, typeId) {
    const wrapper = container.querySelector('[data-drupal-iwc="wrapper"]');
    if (wrapper) {
      wrapper.setAttribute('open', 'open');
    }

    container.querySelectorAll('[data-drupal-iwc="type"]').forEach((el) => {
      const isActive = el.getAttribute('data-drupal-iwc-id') === typeId;
      if (isActive) {
        el.style.display = '';
        el.setAttribute('open', 'open');
      }
      else {
        el.style.display = 'none';
        el.removeAttribute('open');
      }
    });
  }

  function waitForCropPane(container, session, callback) {
    window.setTimeout(() => {
      if (isSessionActive(container, session)) {
        callback();
      }
    }, 100);
  }

  function prepareCropImage($img, typeInstance) {
    if (!$img?.length) {
      return;
    }

    if (typeInstance && typeof typeInstance.destroyCropper === 'function') {
      typeInstance.destroyCropper();
    }

    if ($img.data('cropper')) {
      $img.cropper('destroy');
    }

    if (typeInstance) {
      typeInstance.cropper = null;
    }
  }

  function refreshCropperLayout(typeInstance) {
    if (!typeInstance?.cropper) {
      return;
    }

    if (typeof typeInstance.cropper.resize === 'function') {
      typeInstance.cropper.resize();
    }
    else if (typeof typeInstance.resize === 'function') {
      typeInstance.resize();
    }
  }

  function destroyActiveCropper(container) {
    const typeId = getActiveCropTypeId(container);
    const typeInstance = getCropTypeInstance(getCropInstance(container), typeId);
    if (typeInstance && typeof typeInstance.destroyCropper === 'function') {
      typeInstance.destroyCropper();
    }
    if (typeInstance) {
      typeInstance.visible = false;
    }
  }

  function syncNaturalDimensions(typeInstance, $img) {
    typeInstance.visible = true;
    typeInstance.naturalHeight = parseInt($img.prop('naturalHeight'), 10);
    typeInstance.naturalWidth = parseInt($img.prop('naturalWidth'), 10);
    typeInstance.naturalDelta = typeInstance.originalHeight && typeInstance.naturalHeight
      ? typeInstance.originalHeight / typeInstance.naturalHeight
      : null;
  }

  function restoreSavedCrop(typeInstance, savedCropValues) {
    if (!savedCropValues?.applied || !typeInstance.cropper) {
      return;
    }
    typeInstance.setValues(savedCropValues);
  }

  function commitCrop(typeInstance, $img) {
    if (!typeInstance?.cropper || !$img?.length) {
      return false;
    }

    syncNaturalDimensions(typeInstance, $img);
    typeInstance.cropEnd();

    Object.keys(typeInstance.values).forEach((name) => {
      const input = typeInstance.values[name][0];
      if (input) {
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    return true;
  }

  function activateCropper(typeInstance, $img, session, container, options = {}) {
    const reinit = options.reinit === true;

    return new Promise((resolve) => {
      if (!typeInstance || !$img.length || !isSessionActive(container, session)) {
        resolve(false);
        return;
      }

      syncNaturalDimensions(typeInstance, $img);

      const savedCropValues = typeInstance.getValue('applied')
        ? typeInstance.getValues()
        : null;

      if (typeInstance.cropper && !reinit) {
        resolve(true);
        return;
      }

      let finished = false;
      const finish = (success) => {
        if (finished || !isSessionActive(container, session)) {
          if (!finished) {
            resolve(false);
          }
          return;
        }
        finished = true;
        resolve(!!(success && typeInstance.cropper));
      };

      const startInit = () => {
        if (!isSessionActive(container, session)) {
          finish(false);
          return;
        }

        prepareCropImage($img, typeInstance);

        $img.one('ready.iwc.cropper', () => {
          restoreSavedCrop(typeInstance, savedCropValues);
          refreshCropperLayout(typeInstance);
          finish(true);
        });

        if (typeof typeInstance.initializeCropper === 'function') {
          typeInstance.initializeCropper();
        }

        window.setTimeout(() => {
          if (!isSessionActive(container, session)) {
            finish(false);
            return;
          }
          finish(!!typeInstance.cropper);
        }, 500);
      };

      const beginInit = () => {
        $img.trigger('visible.iwc');
        startInit();
      };

      if ($img[0].complete && $img[0].naturalWidth) {
        beginInit();
      }
      else {
        $img.one('load', beginInit);
      }
    });
  }

  function ensureCropperReady(container, typeId, session, options = {}) {
    const activeSession = session ?? cropState(container).session;

    setActiveCropTypeId(container, typeId);
    openCropType(container, typeId);

    const typeEl = container.querySelector(`[data-drupal-iwc-id="${typeId}"]`);
    const img = typeEl ? typeEl.querySelector('[data-drupal-iwc="image"]') : null;
    if (!img) {
      return Promise.resolve(false);
    }

    const $img = $(img);
    const typeInstance = getCropTypeInstance(getCropInstance(container), typeId);

    return activateCropper(typeInstance, $img, activeSession, container, options);
  }

  function updatePreviewImage(container, typeInstance) {
    const previewImg = container.querySelector(selectors.previewImage);
    const wrapper = container.querySelector(selectors.previewWrapper);
    if (!previewImg || !wrapper || !typeInstance) {
      return;
    }

    if (typeInstance.cropper && typeof typeInstance.cropper.getCroppedCanvas === 'function') {
      const canvas = typeInstance.cropper.getCroppedCanvas({
        maxWidth: 4096,
        maxHeight: 4096,
      });
      if (canvas) {
        previewImg.src = canvas.toDataURL('image/jpeg', 0.92);
        previewImg.removeAttribute('width');
        previewImg.removeAttribute('height');
        previewImg.style.margin = '';
        previewImg.style.maxWidth = '';
        wrapper.style.aspectRatio = '';
        return;
      }
    }

    if (!typeInstance.getValue('applied')) {
      return;
    }

    const width = typeInstance.getValue('width');
    const height = typeInstance.getValue('height');
    const x = typeInstance.getValue('x');
    const y = typeInstance.getValue('y');
    const originalWidth = typeInstance.originalWidth;
    const originalHeight = typeInstance.originalHeight;

    if (!width || !height || !originalWidth || !originalHeight) {
      return;
    }

    wrapper.style.aspectRatio = `${width} / ${height}`;
    const scale = wrapper.clientWidth / width;
    previewImg.style.maxWidth = 'none';
    previewImg.style.width = `${originalWidth * scale}px`;
    previewImg.style.height = `${originalHeight * scale}px`;
    previewImg.style.marginLeft = `${-x * scale}px`;
    previewImg.style.marginTop = `${-y * scale}px`;
  }

  function applyCrop(container) {
    const session = cropState(container).session;
    const typeId = getActiveCropTypeId(container);
    const typeInstance = getCropTypeInstance(getCropInstance(container), typeId);
    const typeEl = typeId ? container.querySelector(`[data-drupal-iwc-id="${typeId}"]`) : null;
    const img = typeEl ? typeEl.querySelector('[data-drupal-iwc="image"]') : null;
    const $img = img ? $(img) : null;

    const finalize = () => {
      if (!isSessionActive(container, session)) {
        return;
      }

      if (typeInstance && $img?.length) {
        commitCrop(typeInstance, $img);
        updatePreviewImage(container, typeInstance);
      }

      if (typeInstance) {
        typeInstance.visible = false;
      }

      if (typeId) {
        setActiveCropTypeId(container, typeId);
      }

      showPreviewMode(container);
    };

    if (typeInstance?.cropper) {
      finalize();
      return;
    }

    ensureCropperReady(container, typeId, session, { reinit: true }).then(finalize);
  }

  function resetCrop(container) {
    const typeId = getActiveCropTypeId(container);
    const typeInstance = getCropTypeInstance(getCropInstance(container), typeId);

    if (typeInstance && typeof typeInstance.reset === 'function') {
      typeInstance.reset();
    }

    const previewImg = container.querySelector(selectors.previewImage);
    const wrapper = container.querySelector(selectors.previewWrapper);
    if (previewImg && wrapper) {
      previewImg.src = previewImg.dataset.mediaLibraryImageCropFullSrc
        || previewImg.dataset.mediaLibraryImageCropOriginalSrc
        || previewImg.src;
      previewImg.style.margin = '';
      previewImg.style.maxWidth = '';
      previewImg.style.width = '';
      previewImg.style.height = '';
      wrapper.style.aspectRatio = '';
    }
  }

  function showCropMode(container) {
    const session = nextSession(container);

    container.querySelector(selectors.previewPane)?.classList.add('is-hidden');
    container.querySelector(selectors.cropPane)?.classList.remove('is-hidden');

    const typeId = getActiveCropTypeId(container);
    if (typeId) {
      setActiveCropTypeId(container, typeId);
      openCropType(container, typeId);
      waitForCropPane(container, session, () => {
        ensureCropperReady(container, typeId, session, { reinit: true });
      });
    }
  }

  function showPreviewMode(container) {
    destroyActiveCropper(container);
    nextSession(container);

    container.querySelector(selectors.cropPane)?.classList.add('is-hidden');
    container.querySelector(selectors.previewPane)?.classList.remove('is-hidden');
  }

  function initContainer(container) {
    if (Drupal.behaviors.imageWidgetCrop?.createInstances) {
      Drupal.behaviors.imageWidgetCrop.createInstances(container);
    }

    const previewImg = container.querySelector(selectors.previewImage);
    if (previewImg && !previewImg.dataset.mediaLibraryImageCropOriginalSrc) {
      previewImg.dataset.mediaLibraryImageCropOriginalSrc = previewImg.dataset.mediaLibraryImageCropFullSrc
        || previewImg.currentSrc
        || previewImg.src;
    }

    const select = getCropTypeSelect(container);
    if (select && !cropState(container).cropType) {
      setActiveCropTypeId(container, select.value);
    }

    container.querySelector(selectors.startCrop)?.addEventListener('click', (e) => {
      e.preventDefault();
      showCropMode(container);
    });

    container.querySelector(selectors.resetCrop)?.addEventListener('click', (e) => {
      e.preventDefault();
      resetCrop(container);
    });

    container.querySelector(selectors.applyCrop)?.addEventListener('click', (e) => {
      e.preventDefault();
      applyCrop(container);
    });

    if (select) {
      select.addEventListener('change', debounce((e) => {
        setActiveCropTypeId(container, e.target.value);
        ensureCropperReady(container, e.target.value, cropState(container).session, { reinit: true });
      }, 150));
    }
  }

  Drupal.behaviors.mediaLibraryImageCropInline = {
    attach(context) {
      once('media-library-image-crop-inline', selectors.root, context).forEach(initContainer);
    },
  };

})(Drupal, once, Drupal.debounce, jQuery);
