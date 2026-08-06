<script>
(function () {
    var tabs = document.querySelectorAll('.fleet-form-tab');
    var panels = document.querySelectorAll('.fleet-tab-panel');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = this.getAttribute('data-tab');

            tabs.forEach(function (item) { item.classList.remove('active'); });
            panels.forEach(function (panel) {
                panel.classList.toggle('active', panel.getAttribute('data-panel') === target);
            });

            this.classList.add('active');
        });
    });

    var colorPicker = document.getElementById('vehicle-color-picker');
    var colorHex = document.getElementById('vehicle-color-hex');
    if (colorPicker && colorHex) {
        colorPicker.addEventListener('input', function () {
            colorHex.value = this.value.toUpperCase();
        });
        colorHex.addEventListener('input', function () {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                colorPicker.value = this.value;
            }
        });
    }

    var openingFuel = document.getElementById('opening-fuel');
    var currentFuelDisplay = document.getElementById('current-fuel-display');
    if (openingFuel && currentFuelDisplay) {
        openingFuel.addEventListener('input', function () {
            currentFuelDisplay.value = this.value || '0';
        });
    }

    var imageInput = document.getElementById('vehicle-image');
    var imagePreview = document.getElementById('vehicle-image-preview');
    var imageName = document.getElementById('vehicle-image-name');
    var defaultPreview = imagePreview ? imagePreview.innerHTML : '';

    if (imageInput && imagePreview && imageName) {
        imageInput.addEventListener('change', function () {
            var file = this.files[0];
            if (!file) {
                imageName.value = 'No file chosen';
                imagePreview.innerHTML = defaultPreview;
                return;
            }

            imageName.value = file.name;

            var reader = new FileReader();
            reader.onload = function (event) {
                imagePreview.innerHTML = '<img src="' + event.target.result + '" alt="Vehicle preview">';
            };
            reader.readAsDataURL(file);
        });
    }

    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    var typeSelect = document.getElementById('vehicle-type-select');
    var manufacturerSelect = document.getElementById('vehicle-manufacturer-select');
    var modelSelect = document.getElementById('vehicle-model-select');
    var modelModalManufacturerSelect = document.getElementById('vehicle-model-manufacturer-select');

    function appendSelectOption(select, value, label, selected) {
        if (!select) {
            return;
        }

        var exists = Array.prototype.some.call(select.options, function (option) {
            return option.value === value;
        });

        if (!exists) {
            var option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            select.appendChild(option);
        }

        if (selected) {
            select.value = value;
        }
    }

    function showLookupErrors(errorsEl, result) {
        if (!errorsEl) {
            return;
        }

        var messages = [];
        if (result.data && result.data.errors) {
            Object.keys(result.data.errors).forEach(function (key) {
                result.data.errors[key].forEach(function (message) {
                    messages.push(message);
                });
            });
        } else if (result.data && result.data.message) {
            messages.push(result.data.message);
        } else {
            messages.push('Could not save. Please try again.');
        }

        errorsEl.innerHTML = '<ul><li>' + messages.join('</li><li>') + '</li></ul>';
        errorsEl.hidden = false;
    }

    function hideLookupErrors(errorsEl) {
        if (errorsEl) {
            errorsEl.hidden = true;
            errorsEl.innerHTML = '';
        }
    }

    function setupLookupModal(modalId, openBtnId, formId, errorsId, submitId, closeAttr, onOpen) {
        var modal = document.getElementById(modalId);
        var openBtn = document.getElementById(openBtnId);
        var form = document.getElementById(formId);
        var errorsEl = document.getElementById(errorsId);
        var submitBtn = document.getElementById(submitId);

        if (!modal || !form) {
            return null;
        }

        function openModal() {
            hideLookupErrors(errorsEl);
            if (typeof onOpen === 'function') {
                onOpen();
            }
            modal.hidden = false;
            document.body.classList.add('fleet-modal-open');
        }

        function closeModal() {
            modal.hidden = true;
            document.body.classList.remove('fleet-modal-open');
            hideLookupErrors(errorsEl);
        }

        if (openBtn) {
            openBtn.addEventListener('click', openModal);
        }

        modal.querySelectorAll('[' + closeAttr + ']').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });

        return {
            modal: modal,
            form: form,
            errorsEl: errorsEl,
            submitBtn: submitBtn,
            openModal: openModal,
            closeModal: closeModal
        };
    }

    function submitLookupForm(form, submitBtn, errorsEl, url, onSuccess) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            hideLookupErrors(errorsEl);

            if (submitBtn) {
                submitBtn.disabled = true;
            }

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken ? csrfToken.getAttribute('content') : '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new FormData(form)
            })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    showLookupErrors(errorsEl, result);
                    return;
                }

                onSuccess(result.data);
                form.reset();
            })
            .catch(function () {
                showLookupErrors(errorsEl, { data: { message: 'Network error. Please try again.' } });
            })
            .finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            });
        });
    }

    function syncModelModalManufacturer() {
        if (!manufacturerSelect || !modelModalManufacturerSelect) {
            return;
        }

        var selectedManufacturer = manufacturerSelect.value;
        if (!selectedManufacturer) {
            modelModalManufacturerSelect.value = '';
            return;
        }

        var matched = false;
        Array.prototype.forEach.call(modelModalManufacturerSelect.options, function (option) {
            if (option.textContent === selectedManufacturer) {
                modelModalManufacturerSelect.value = option.value;
                matched = true;
            }
        });

        if (!matched) {
            modelModalManufacturerSelect.value = '';
        }
    }

    var typeModal = setupLookupModal(
        'vehicle-add-type-modal',
        'open-add-vehicle-type-modal',
        'vehicle-add-type-form',
        'vehicle-add-type-errors',
        'vehicle-add-type-submit',
        'data-close-vehicle-type-modal'
    );

    if (typeModal) {
        submitLookupForm(
            typeModal.form,
            typeModal.submitBtn,
            typeModal.errorsEl,
            @json(route('vehicles.types.store-quick')),
            function (data) {
                var vehicleType = data.vehicle_type;
                appendSelectOption(typeSelect, vehicleType.name, vehicleType.name, true);
                typeModal.closeModal();
            }
        );
    }

    var manufacturerModal = setupLookupModal(
        'vehicle-add-manufacturer-modal',
        'open-add-manufacturer-modal',
        'vehicle-add-manufacturer-form',
        'vehicle-add-manufacturer-errors',
        'vehicle-add-manufacturer-submit',
        'data-close-vehicle-manufacturer-modal'
    );

    if (manufacturerModal) {
        submitLookupForm(
            manufacturerModal.form,
            manufacturerModal.submitBtn,
            manufacturerModal.errorsEl,
            @json(route('vehicles.manufacturers.store-quick')),
            function (data) {
                var manufacturer = data.manufacturer;
                appendSelectOption(manufacturerSelect, manufacturer.name, manufacturer.name, true);
                appendSelectOption(modelModalManufacturerSelect, String(manufacturer.id), manufacturer.name, false);
                manufacturerModal.closeModal();
            }
        );
    }

    var modelModal = setupLookupModal(
        'vehicle-add-model-modal',
        'open-add-model-modal',
        'vehicle-add-model-form',
        'vehicle-add-model-errors',
        'vehicle-add-model-submit',
        'data-close-vehicle-model-modal',
        syncModelModalManufacturer
    );

    if (modelModal) {
        submitLookupForm(
            modelModal.form,
            modelModal.submitBtn,
            modelModal.errorsEl,
            @json(route('vehicles.models.store-quick')),
            function (data) {
                var model = data.model;
                appendSelectOption(modelSelect, model.name, model.name, true);
                modelModal.closeModal();
            }
        );
    }
})();
</script>
