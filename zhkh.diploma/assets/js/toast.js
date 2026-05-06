/**
 * Toast-уведомления ДомУчет
 * Звук: мелодичный колокольчик (короткие ноты синусом с затуханием)
 */
(function () {

    var AudioCtx = window.AudioContext || window.webkitAudioContext;

    // Ноты: success — восходящий аккорд до-ми-соль
    //        error   — нисходящий ля-фа
    //        warning — двойной ми-ре
    //        info    — одиночный до
    var SOUNDS = {
        success: [{ f:523,t:0,d:.12 },{ f:659,t:.1,d:.12 },{ f:784,t:.2,d:.18 }],
        error:   [{ f:440,t:0,d:.14 },{ f:349,t:.13,d:.18 }],
        warning: [{ f:494,t:0,d:.10 },{ f:440,t:.11,d:.14 }],
        info:    [{ f:523,t:0,d:.15 }],
    };

    function playBell(type) {
        if (!AudioCtx) return;
        try {
            var ctx   = new AudioCtx();
            var notes = SOUNDS[type] || SOUNDS.info;
            notes.forEach(function(n) {
                var osc  = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'sine';
                osc.frequency.setValueAtTime(n.f, ctx.currentTime + n.t);
                gain.gain.setValueAtTime(0, ctx.currentTime + n.t);
                gain.gain.linearRampToValueAtTime(0.18, ctx.currentTime + n.t + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + n.t + n.d + 0.15);
                osc.start(ctx.currentTime + n.t);
                osc.stop(ctx.currentTime + n.t + n.d + 0.2);
            });
        } catch(e) {}
    }

    // ── Контейнер ─────────────────────────────────────────────────────
    function getContainer() {
        var c = document.getElementById('toast-container');
        if (!c) { c = document.createElement('div'); c.id = 'toast-container'; document.body.appendChild(c); }
        return c;
    }

    var ICONS = {
        success: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414L8.414 15l-4.121-4.121a1 1 0 011.414-1.414L8.414 12.172l6.879-6.879a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>',
        error:   '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
        warning: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
        info:    '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
    };

    window.showToast = function (message, type, duration, sound) {
        type     = type     !== undefined ? type     : 'info';
        duration = duration !== undefined ? duration : 4000;
        sound    = sound    !== undefined ? sound    : true;
        if (sound) playBell(type);

        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.innerHTML =
            '<span class="toast-icon">'+(ICONS[type]||ICONS.info)+'</span>'+
            '<span class="toast-msg">'+message+'</span>'+
            '<button class="toast-close" onclick="this.parentElement.remove()" title="Закрыть">'+
            '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>'+
            '</button>'+
            '<div class="toast-progress" style="animation-duration:'+duration+'ms"></div>';

        getContainer().appendChild(toast);
        requestAnimationFrame(function(){ requestAnimationFrame(function(){ toast.classList.add('toast-show'); }); });

        var timer = setTimeout(function(){ removeToast(toast); }, duration);
        toast.addEventListener('mouseenter', function(){ clearTimeout(timer); });
        toast.addEventListener('mouseleave', function(){ timer = setTimeout(function(){ removeToast(toast); }, 1200); });
    };

    function removeToast(t) {
        t.classList.remove('toast-show'); t.classList.add('toast-hide');
        setTimeout(function(){ t.remove(); }, 350);
    }

    // ── Flash от PHP (сессия или cookie) ──────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        // Flash через data-атрибут body (устанавливается renderFooter)
        var msg  = document.body.dataset.flashMsg;
        var type = document.body.dataset.flashType || 'info';
        if (msg) { showToast(msg, type); return; }

        // Flash через cookie (для страниц без renderFooter — login и др.)
        var cookieMsg = getCookie('_flash_msg');
        if (cookieMsg) {
            var cookieType = getCookie('_flash_type') || 'info';
            deleteCookie('_flash_msg'); deleteCookie('_flash_type');
            showToast(cookieMsg, cookieType);
            return;
        }

        // ── Авто-уведомления ──────────────────────────────────────────
        var path = window.location.pathname;

        // Показания счётчиков
        if (path.indexOf('meters') !== -1) {
            var mForm = document.querySelector('form');
            if (mForm) mForm.addEventListener('submit', function(){ showToast('Отправляем показания...', 'info', 2500, false); });
            var today = new Date();
            var daysLeft = new Date(today.getFullYear(), today.getMonth()+1, 0).getDate() - today.getDate();
            if (daysLeft <= 5) setTimeout(function(){ showToast('До конца месяца '+daysLeft+' дн. — не забудьте передать показания!', 'warning', 6000); }, 800);
        }

        // Неоплаченные квитанции
        if (path.indexOf('invoices') !== -1) {
            var unpaid = document.querySelectorAll('.badge-unpaid');
            if (unpaid.length > 0) {
                var n = unpaid.length;
                var sfx = n===1?'ая':n<5?'ые':'ых', wrd = n===1?'ия':n<5?'ии':'ий';
                setTimeout(function(){ showToast('У вас '+n+' неоплаченн'+sfx+' квитанц'+wrd, 'warning', 5000); }, 900);
            }
        }

        // Профиль
        var pwForm = document.getElementById('passwordForm');
        if (pwForm) pwForm.addEventListener('submit', function(){ showToast('Сохраняем новый пароль...', 'info', 2000, false); });
        var profileForm = document.getElementById('profileForm');
        if (profileForm) profileForm.addEventListener('submit', function(){ showToast('Сохраняем изменения профиля...', 'info', 2000, false); });

        // Приветствие на главной
        var isIndex = path.match(/\/(index\.php)?$/) || path.endsWith('index.php');
        if (isIndex && !sessionStorage.getItem('dom_welcomed')) {
            sessionStorage.setItem('dom_welcomed','1');
            var hour = new Date().getHours();
            var greet = hour<12?'Доброе утро':hour<18?'Добрый день':'Добрый вечер';
            var nameEl = document.querySelector('.user-chip-name');
            var uname  = nameEl ? ', '+nameEl.textContent.trim().split(' ')[0] : '';
            setTimeout(function(){ showToast(greet+uname+'! Добро пожаловать в ДомУчет.', 'info', 4000); }, 700);
        }
    });

    function getCookie(name) {
        var m = document.cookie.match('(?:^|;)\\s*'+name+'=([^;]*)');
        return m ? decodeURIComponent(m[1]) : null;
    }
    function deleteCookie(name) {
        document.cookie = name+'=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
    }

})();
