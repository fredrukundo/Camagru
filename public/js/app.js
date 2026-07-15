(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initEditor();
        initLikes();
        initComments();
        initPolling();
        initPostPolling();
    });
    

    /* ========================================
       Client-logic: AJAX, Webcam, REAL-TIME POLLING
    ======================================== */


    function initPolling() {
        var gallery = document.getElementById('gallery-container');
        if (!gallery) return;

        var POLL_INTERVAL = 5000; // 5 seconds

        function poll() {
            // Collect all visible image IDs
            var cards = gallery.querySelectorAll('.gallery-card');
            var ids = [];
            cards.forEach(function (card) {
                var id = card.id.replace('image-', '');
                if (id) ids.push(id);
            });

            if (ids.length === 0) return;

            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/api/updates?ids=' + ids.join(','), true);

            xhr.onload = function () {
                if (xhr.status !== 200) return;

                try {
                    var data = JSON.parse(xhr.responseText);
                } catch (e) {
                    return;
                }

                if (!data.success || !data.images) return;

                data.images.forEach(function (img) {
                    updateComments(img.id, img.comments);
                    updateLikes(img.id, img.like_count, img.user_liked);
                });
            };

            xhr.onerror = function () {
                // Silent fail — will retry next interval
            };

            xhr.send();
        }

        function updateComments(imageId, comments) {
            var commentsDiv = document.getElementById('comments-' + imageId);
            if (!commentsDiv) return;

            var currentCount = commentsDiv.querySelectorAll('.comment').length;

            // Only update if there are new comments
            if (comments.length <= currentCount) return;

            // Clear and rebuild all comments
            commentsDiv.innerHTML = '';

            comments.forEach(function (c) {
                var div = document.createElement('div');
                div.className = 'comment';

                var strong = document.createElement('strong');
                strong.textContent = c.username;

                div.appendChild(strong);
                div.appendChild(
                    document.createTextNode(' ' + c.content)
                );

                commentsDiv.appendChild(div);
            });

            // Highlight the latest comment briefly
            var lastComment = commentsDiv.lastElementChild;
            if (lastComment && comments.length > currentCount) {
                lastComment.style.backgroundColor = '#fffde7';
                lastComment.style.transition = 'background-color 2s';
                setTimeout(function () {
                    lastComment.style.backgroundColor = 'transparent';
                }, 2000);
            }
        }

        function updateLikes(imageId, count, userLiked) {
            // Update count
            var countEl = document.getElementById('like-count-' + imageId);
            if (countEl) {
                var newText = count + ' like' + (count !== 1 ? 's' : '');
                if (countEl.textContent.trim() !== newText) {
                    countEl.textContent = newText;
                }
            }

            // Update button
            var card = document.getElementById('image-' + imageId);
            if (!card) return;

            var likeBtn = card.querySelector('.like-btn');
            if (likeBtn) {
                var expected = userLiked ? '❤️' : '🤍';
                if (likeBtn.textContent.trim() !== expected.trim()) {
                    likeBtn.textContent = expected;
                }
            }
        }

        // Start polling
        setInterval(poll, POLL_INTERVAL);

        // Also poll immediately on first load
        setTimeout(poll, 1000);
    }

    /* ========================================
       COMMENTS (AJAX)
    ======================================== */
    function initComments() {
        var boxes = document.querySelectorAll('.comment-box');
        if (!boxes.length) return;

        boxes.forEach(function (box) {
            if (box.dataset.bound === 'true') return;
            box.dataset.bound = 'true';

            var btn   = box.querySelector('.comment-post-btn');
            var input = box.querySelector('.comment-input');

            if (!btn || !input) return;

            function postComment() {
                var imageId   = box.getAttribute('data-image-id');
                var csrfToken = document.getElementById('csrf-token');
                var content   = input.value.trim();

                if (!content) return;
                if (!csrfToken) {
                    alert('Session expired. Reload page.');
                    return;
                }

                btn.disabled = true;
                btn.textContent = '...';

                var xhr = new XMLHttpRequest();
                xhr.open('POST', '/comment', true);

                xhr.onload = function () {
                    try {
                        var data = JSON.parse(xhr.responseText);
                    } catch (err) {
                        btn.disabled = false;
                        btn.textContent = 'Post';
                        return;
                    }

                    if (data.success) {
                        var commentsDiv = document.getElementById(
                            'comments-' + imageId
                        );
                        if (commentsDiv) {
                            var newComment = document.createElement('div');
                            newComment.className = 'comment';

                            var strong = document.createElement('strong');
                            strong.textContent = data.username;

                            newComment.appendChild(strong);
                            newComment.appendChild(
                                document.createTextNode(' ' + data.content)
                            );
                            commentsDiv.appendChild(newComment);

                            newComment.style.backgroundColor = '#e3f2fd';
                            newComment.style.transition = 'background-color 2s';
                            setTimeout(function () {
                                newComment.style.backgroundColor = 'transparent';
                            }, 2000);

                            newComment.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                        }
                        input.value = '';
                    } else {
                        alert(data.error || 'Error posting comment.');
                    }

                    btn.disabled = false;
                    btn.textContent = 'Post';
                };

                xhr.onerror = function () {
                    alert('Network error.');
                    btn.disabled = false;
                    btn.textContent = 'Post';
                };

                var formData = new FormData();
                formData.append('image_id', imageId);
                formData.append('content', content);
                formData.append('csrf_token', csrfToken.value);

                xhr.send(formData);
            }

            btn.addEventListener('click', postComment);

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    postComment();
                }
            });
        });
    }

    /* ========================================
       LIKES (AJAX)
    ======================================== */
    function initLikes() {
        var likeBtns  = document.querySelectorAll('.like-btn');
        var csrfInput = document.getElementById('csrf-token');

        if (!likeBtns.length || !csrfInput) return;

        likeBtns.forEach(function (btn) {
            if (btn.dataset.bound === 'true') return;
            btn.dataset.bound = 'true';

            btn.addEventListener('click', function () {
                var imageId = this.getAttribute('data-image-id');
                var button  = this;

                var formData = new FormData();
                formData.append('image_id', imageId);
                formData.append('csrf_token', csrfInput.value);

                fetch('/like', {
                    method: 'POST',
                    body: formData
                })
                .then(function (res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(function (data) {
                    if (data.success) {
                        button.textContent = data.liked ? '❤️' : '🤍';
                        var countEl = document.getElementById(
                            'like-count-' + imageId
                        );
                        if (countEl) {
                            countEl.textContent =
                                data.count + ' like' +
                                (data.count !== 1 ? 's' : '');
                        }
                    }
                })
                .catch(function () {});
            });
        });
    }

    /* ========================================
       EDITOR
    ======================================== */
    function initEditor() {
        var overlaysList = document.getElementById('overlays-list');
        if (!overlaysList) return;

        var webcam         = document.getElementById('webcam');
        var canvas         = document.getElementById('canvas');
        var captureBtn     = document.getElementById('capture-btn');
        var uploadLayer = document.getElementById('upload-stickers-layer');
        var webcamError    = document.getElementById('webcam-error');
        var uploadOverlay  = document.getElementById('upload-overlay');
        var uploadBtn      = document.getElementById('upload-btn');
        var uploadHint     = document.getElementById('upload-hint');
        var fileInput      = document.getElementById('user-image-input');
        var uploadPreviewC = document.getElementById('upload-preview-container');
        var uploadPreviewI = document.getElementById('upload-preview-img');
        var webcamLayer = document.getElementById('webcam-stickers-layer');
        var uploadForm     = document.getElementById('upload-form');
        var csrfInput      = document.getElementById('csrf-token');

        
        var stickers = [];
        var draggingSticker = null;
        var dragOffsetX = 0;
        var dragOffsetY = 0;
        var webcamReady     = false;
        var fileSelected    = false;

        if (webcam && navigator.mediaDevices &&
            navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices
                .getUserMedia({ video: { width: 640, height: 480 } })
                .then(function (stream) {
                    webcam.srcObject = stream;
                    webcamReady = true;
                    updateButtons();
                })
                .catch(function () {
                    showWebcamError();
                });
        } else {
            showWebcamError();
        }

        function addSticker(file) {

            stickers.push({
                id: Date.now() + Math.random(),
                file: file,
                x: 20 + (stickers.length * 25),
                y: 20 + (stickers.length * 25),
                width: 120,
                height: 120
            });

            renderStickers();
        }

        function renderStickers() {

                if (uploadLayer)
                    uploadLayer.innerHTML = "";

                if (webcamLayer)
                    webcamLayer.innerHTML = "";

                stickers.forEach(function (sticker) {

                    if (uploadLayer)
                        createSticker(uploadLayer, sticker);

                    if (webcamLayer)
                        createSticker(webcamLayer, sticker);

                });

        }

        function createSticker(layer, sticker) {

            var wrapper = document.createElement("div");

            wrapper.className = "sticker-wrapper";
            wrapper.style.position = "absolute";
            wrapper.style.left = sticker.x + "px";
            wrapper.style.top = sticker.y + "px";
            wrapper.dataset.id = sticker.id;

            var img = document.createElement("img");

            img.src = "/overlays/" + sticker.file;
            img.className = "sticker";
            img.style.width = sticker.width + "px";
            img.style.height = sticker.height + "px";
            img.draggable = false;

            wrapper.appendChild(img);

            wrapper.addEventListener("mousedown", function (e) {

                e.preventDefault();

                draggingSticker = sticker;

                dragOffsetX = e.clientX - sticker.x;
                dragOffsetY = e.clientY - sticker.y;

            });

            layer.appendChild(wrapper);
        }

        function showWebcamError() {
            if (webcam) webcam.style.display = 'none';
            if (webcamError) webcamError.style.display = 'block';
            webcamReady = false;
            updateButtons();
        }

        function updateButtons() {
            if (captureBtn) {
                captureBtn.disabled = !webcamReady
            }
            if (uploadBtn) {
                uploadBtn.disabled = !fileSelected;
            }
            if (uploadHint) {
                uploadHint.style.display =
                    fileSelected ? 'none' : 'block';
            }
        }

        var thumbs = overlaysList.querySelectorAll(".overlay-thumb");
        thumbs.forEach(function (thumb) {

            thumb.addEventListener("click", function () {

                addSticker(this.dataset.overlay);

            });

        });


        if (fileInput) {
            fileInput.addEventListener('change', function () {
                if (this.files && this.files.length > 0) {
                    fileSelected = true;
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        if (uploadPreviewI)
                            uploadPreviewI.src = e.target.result;
                        if (uploadPreviewC)
                            uploadPreviewC.style.display = 'block';
                       
                    };
                    reader.readAsDataURL(this.files[0]);
                } else {
                    fileSelected = false;
                    if (uploadPreviewC)
                        uploadPreviewC.style.display = 'none';
                }
                updateButtons();
            });
        }

        if (captureBtn) {
            captureBtn.addEventListener('click', function () {
                if ( !webcamReady || !webcam) return;
                if (!csrfInput) return;

                var w = webcam.videoWidth || 640;
                var h = webcam.videoHeight || 480;
                canvas.width = w;
                canvas.height = h;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(webcam, 0, 0, w, h);

                var imageData = canvas.toDataURL('image/png');

                captureBtn.disabled = true;
                captureBtn.textContent = '⏳ Processing...';

                var formData = new FormData();
                formData.append('image_data', imageData);

                formData.append(
                    'stickers',
                    JSON.stringify(stickers)
                );
                

                formData.append(
                    'csrf_token',
                    csrfInput.value
                );

                fetch('/capture', { method: 'POST', body: formData })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert(data.error || 'Capture error');
                            captureBtn.disabled = false;
                            captureBtn.textContent = '📸 Take Photo';
                        }
                    })
                    .catch(function () {
                        alert('Network error. Try again.');
                        captureBtn.disabled = false;
                        captureBtn.textContent = '📸 Take Photo';
                    });
            });
        }

        if (uploadForm) {

            uploadForm.addEventListener('submit', function (e) {

                if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                    e.preventDefault();
                    alert('Choose an image file.');
                    return;
                }

                var input = document.getElementById("stickers-input");

                if (!input) {

                    input = document.createElement("input");

                    input.type = "hidden";
                    input.name = "stickers";
                    input.id = "stickers-input";

                    uploadForm.appendChild(input);
                }

                input.value = JSON.stringify(stickers);
                

            });

        }

        document.addEventListener("mousemove", function (e) {

            if (!draggingSticker)
                return;

            draggingSticker.x = e.clientX - dragOffsetX;
            draggingSticker.y = e.clientY - dragOffsetY;

            renderStickers();

        });

        document.addEventListener("mouseup", function () {

            draggingSticker = null;

        });
    }

    function initPostPolling() {
        var gallery = document.getElementById('gallery-container');
        if (!gallery) return;

        var knownIds = [];
        gallery.querySelectorAll('.gallery-card').forEach(function (card) {
            knownIds.push(card.id.replace('image-', ''));
        });

        setInterval(function () {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/api/new-posts?after=' + (knownIds[0] || 0), true);

            xhr.onload = function () {
                if (xhr.status !== 200) return;

                try {
                    var data = JSON.parse(xhr.responseText);
                } catch (e) {
                    return;
                }

                if (!data.success || !data.images || !data.images.length) return;

                data.images.forEach(function (img) {
                    if (knownIds.indexOf(String(img.id)) !== -1) return;

                    knownIds.unshift(String(img.id));

                    var card = document.createElement('div');
                    card.className = 'gallery-card';
                    card.id = 'image-' + img.id;
                    card.style.backgroundColor = '#fffde7';
                    card.style.transition = 'background-color 3s';

                    var html = '<div class="gallery-card-header">'
                        + escHtml(img.username) + '</div>'
                        + '<img src="/uploads/' + escHtml(img.filename) + '">'
                        + '<div class="gallery-card-actions">';

                    if (data.logged_in) {
                        html += '<button class="like-btn" data-image-id="'
                            + img.id + '">🤍</button>';
                    } else {
                        html += '<span>❤️</span>';
                    }

                    html += '<span class="like-count" id="like-count-'
                        + img.id + '">0 likes</span>'
                        + '</div>'
                        + '<div class="gallery-card-comments" id="comments-'
                        + img.id + '"></div>';

                    if (data.logged_in) {
                        html += '<div class="comment-box" data-image-id="'
                            + img.id + '">'
                            + '<input type="text" class="comment-input" '
                            + 'placeholder="Add a comment..." maxlength="500" '
                            + 'autocomplete="off">'
                            + '<button type="button" class="comment-post-btn">'
                            + 'Post</button></div>';
                    }

                    card.innerHTML = html;
                    gallery.insertBefore(card, gallery.firstChild);

                    setTimeout(function () {
                        card.style.backgroundColor = 'white';
                    }, 3000);
                });

                // Rebind new elements
                initLikes();
                initComments();
            };

            xhr.send();
        }, 5000);
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

})();