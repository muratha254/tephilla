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

    var photoInput = document.getElementById('driver-photo-input');
    var photoPreview = document.getElementById('driver-photo-preview');
    var captureBtn = document.getElementById('driver-capture-btn');
    var webcam = document.getElementById('driver-webcam');
    var canvas = document.getElementById('driver-webcam-canvas');
    var webcamActive = false;
    var defaultPreview = photoPreview ? photoPreview.innerHTML : '';

    function showPreviewFromFile(file) {
        if (!photoPreview || !file) return;

        var reader = new FileReader();
        reader.onload = function (event) {
            photoPreview.innerHTML = '<img src="' + event.target.result + '" alt="Driver photo">';
        };
        reader.readAsDataURL(file);
    }

    if (photoInput && photoPreview) {
        photoInput.addEventListener('change', function () {
            if (this.files[0]) {
                showPreviewFromFile(this.files[0]);
            }
        });
    }

    if (captureBtn && webcam && canvas && photoPreview) {
        captureBtn.addEventListener('click', function () {
            if (!webcamActive) {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert('Webcam is not supported in this browser.');
                    return;
                }

                navigator.mediaDevices.getUserMedia({ video: true })
                    .then(function (stream) {
                        webcam.srcObject = stream;
                        webcam.hidden = false;
                        photoPreview.hidden = true;
                        webcamActive = true;
                        captureBtn.innerHTML = '<i class="fa fa-camera"></i> Take Snapshot';
                    })
                    .catch(function () {
                        alert('Unable to access webcam.');
                    });
                return;
            }

            canvas.width = webcam.videoWidth;
            canvas.height = webcam.videoHeight;
            canvas.getContext('2d').drawImage(webcam, 0, 0);

            canvas.toBlob(function (blob) {
                if (!blob || !photoInput) return;

                var file = new File([blob], 'driver-photo.png', { type: 'image/png' });
                var dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                photoInput.files = dataTransfer.files;

                photoPreview.innerHTML = '<img src="' + canvas.toDataURL('image/png') + '" alt="Driver photo">';
                photoPreview.hidden = false;
                webcam.hidden = true;

                if (webcam.srcObject) {
                    webcam.srcObject.getTracks().forEach(function (track) { track.stop(); });
                    webcam.srcObject = null;
                }

                webcamActive = false;
                captureBtn.innerHTML = '<i class="fa fa-camera"></i> Capture Photo';
            }, 'image/png');
        });
    }
})();
</script>
