/**
 * Travel Platform AI Chat Widget
 * Self-contained, zero external dependencies.
 * Integration: <script src="/assets/chat-widget.js" defer></script>
 * Loaded by partials/footer.php only when user is logged in.
 */
(function () {
  'use strict';

  var state = {
    open: false,
    messages: [],
    pageContext: { url: location.href, title: document.title, ts: Date.now() },
    currentTripId: null, // set by data attributes on the page
  };
  var bubble, panel, msgContainer, inputEl, sendBtn, closeBtn, resizeHandle;

  // ───── CSS injected ─────
  var CSS =
    '.tp-chat *{box-sizing:border-box;margin:0;padding:0}' +
    '.tp-chat-bubble{position:fixed;z-index:2147483646;bottom:24px;right:24px;' +
      'width:64px;height:64px;border-radius:999px;' +
      'background:#0f766e;color:#fff;cursor:pointer;' +
      'display:flex;align-items:center;justify-content:center;' +
      'box-shadow:0 4px 16px rgba(15,118,110,0.35);' +
      'transition:transform .15s,box-shadow .15s;user-select:none;}' +
    '.tp-chat-bubble:hover{transform:scale(1.08);box-shadow:0 6px 24px rgba(15,118,110,0.45);}' +
    '.tp-chat-bubble svg{width:32px;height:32px;fill:currentColor;}' +
    '.tp-chat-panel{position:fixed;z-index:2147483647;bottom:24px;right:24px;' +
      'width:380px;height:500px;max-width:calc(100vw - 48px);max-height:calc(100vh - 48px);' +
      'background:#fff;border-radius:12px;' +
      'box-shadow:0 8px 40px rgba(0,0,0,0.18);' +
      'display:none;flex-direction:column;overflow:hidden;' +
      'font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI","Noto Sans TC",sans-serif;' +
      'font-size:14px;line-height:1.5;color:#172033;}' +
    '.tp-chat-panel.open{display:flex;}' +
    '.tp-chat-header{display:flex;align-items:center;justify-content:space-between;' +
      'padding:12px 16px;background:#0f766e;color:#fff;font-weight:700;font-size:15px;flex-shrink:0;}' +
    '.tp-chat-close{background:none;border:none;color:#fff;font-size:22px;cursor:pointer;' +
      'line-height:1;padding:2px 6px;border-radius:6px;}' +
    '.tp-chat-close:hover{background:rgba(255,255,255,0.15);}' +
    '.tp-chat-messages{flex:1;overflow-y:auto;padding:12px 16px;display:flex;flex-direction:column;gap:10px;}' +
    '.tp-chat-msg{max-width:85%;padding:8px 12px;border-radius:10px;word-break:break-word;white-space:pre-wrap;}' +
    '.tp-chat-msg.user{align-self:flex-end;background:#0f766e;color:#fff;border-bottom-right-radius:4px;}' +
    '.tp-chat-msg.ai{align-self:flex-start;background:#f0f2f5;color:#172033;border-bottom-left-radius:4px;}' +
    '.tp-chat-msg.system{align-self:center;font-size:12px;color:#667085;background:transparent;padding:4px 8px;}' +
    '.tp-chat-msg a{color:inherit;text-decoration:underline;}' +
    '.tp-chat-msg strong{font-weight:700;}' +
    '.tp-chat-action-bar{display:flex;gap:8px;margin-top:6px;flex-wrap:wrap;}' +
    '.tp-chat-action-btn{font-size:12px;padding:5px 12px;border-radius:8px;border:1px solid #0f766e;' +
      'color:#0f766e;background:#fff;cursor:pointer;font-weight:600;transition:all .12s;white-space:nowrap;}' +
    '.tp-chat-action-btn:hover{background:#0f766e;color:#fff;}' +
    '.tp-chat-spot-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 10px;margin:4px 0;' +
      'display:flex;justify-content:space-between;align-items:center;}' +
    '.tp-chat-spot-card .name{font-weight:600;font-size:13px;}' +
    '.tp-chat-spot-card .addr{font-size:11px;color:#667085;}' +
    '.tp-chat-input-area{display:flex;gap:8px;padding:10px 12px;border-top:1px solid #e2e8f0;flex-shrink:0;}' +
    '.tp-chat-input{flex:1;border:1px solid #d1d9e6;border-radius:8px;padding:9px 12px;font:inherit;font-size:14px;outline:none;}' +
    '.tp-chat-input:focus{border-color:#0f766e;box-shadow:0 0 0 3px rgba(15,118,110,0.14);}' +
    '.tp-chat-send{background:#0f766e;border:none;color:#fff;width:38px;height:38px;border-radius:8px;cursor:pointer;' +
      'display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:18px;transition:background .12s;}' +
    '.tp-chat-send:hover{background:#115e59;}' +
    '.tp-chat-send:disabled{background:#94a3b8;cursor:default;}' +
    '.tp-chat-typing{align-self:flex-start;display:flex;gap:4px;padding:10px 14px;' +
      'background:#f0f2f5;border-radius:10px;border-bottom-left-radius:4px;}' +
    '.tp-chat-typing span{width:8px;height:8px;border-radius:999px;background:#94a3b8;' +
      'animation:tp-chat-bounce 1.2s infinite ease-in-out;}' +
    '.tp-chat-typing span:nth-child(2){animation-delay:.16s}' +
    '.tp-chat-typing span:nth-child(3){animation-delay:.32s}' +
    '@keyframes tp-chat-bounce{0%,60%,100%{transform:translateY(0);opacity:.4}30%{transform:translateY(-4px);opacity:1}}' +
    '.tp-chat-resize{position:absolute;bottom:0;right:0;width:16px;height:16px;' +
      'cursor:nwse-resize;opacity:.3;' +
      'background:linear-gradient(135deg,transparent 40%,#667085 40%,#667085 50%,transparent 50%);}' +
    '.tp-chat-resize:hover{opacity:.6;}' +
    '@media(max-width:520px){' +
      '.tp-chat-bubble{bottom:16px;right:16px;}' +
      '.tp-chat-panel{bottom:0;right:0;width:100%;max-width:100%;height:100%;max-height:100%;border-radius:0;}}';

  function injectCSS() { var s = document.createElement('style'); s.textContent = CSS; document.head.appendChild(s); }

  function escapeHtml(t) { var d = document.createElement('div'); d.appendChild(document.createTextNode(t)); return d.innerHTML; }

  function renderMarkdown(text) {
    return escapeHtml(text)
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
  }

  function buildDOM() {
    bubble = document.createElement('div');
    bubble.className = 'tp-chat-bubble';
    bubble.setAttribute('aria-label', '開啟 AI 助手');
    bubble.innerHTML = '<svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/><circle cx="9" cy="10" r="1.5"/><circle cx="15" cy="10" r="1.5"/></svg>';
    document.body.appendChild(bubble);

    panel = document.createElement('div');
    panel.className = 'tp-chat-panel';
    panel.innerHTML =
      '<div class="tp-chat-header"><span>AI 助手</span><button class="tp-chat-close" aria-label="關閉">×</button></div>' +
      '<div class="tp-chat-messages"></div>' +
      '<div class="tp-chat-input-area">' +
        '<input class="tp-chat-input" type="text" placeholder="輸入訊息..." autocomplete="off">' +
        '<button class="tp-chat-send" aria-label="傳送">➤</button>' +
      '</div>' +
      '<div class="tp-chat-resize"></div>';
    document.body.appendChild(panel);

    msgContainer = panel.querySelector('.tp-chat-messages');
    inputEl = panel.querySelector('.tp-chat-input');
    sendBtn = panel.querySelector('.tp-chat-send');
    closeBtn = panel.querySelector('.tp-chat-close');
    resizeHandle = panel.querySelector('.tp-chat-resize');

    // Read current trip ID from the page if on editor/trip page
    var tripIdEl = document.querySelector('input[name="trip_id"]');
    if (tripIdEl && tripIdEl.value) {
      state.currentTripId = parseInt(tripIdEl.value);
    }
  }

  /**
   * Show an action button below an AI message.
   * @param {HTMLElement} msgEl   The AI message DOM element to attach under
   * @param {string} label        Button text (e.g. "填入編輯器")
   * @param {function} onClick    Callback when clicked
   */
  function addActionButton(msgEl, label, onClick) {
    var bar = msgEl.querySelector('.tp-chat-action-bar');
    if (!bar) {
      bar = document.createElement('div');
      bar.className = 'tp-chat-action-bar';
      msgEl.appendChild(bar);
    }
    var btn = document.createElement('button');
    btn.className = 'tp-chat-action-btn';
    btn.textContent = label;
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      onClick();
    });
    bar.appendChild(btn);
  }

  /**
   * Add a "填入編輯器" button that POSTs to ai-fill-editor.php
   */
  function addFillEditorButton(msgEl, tripId, content) {
    addActionButton(msgEl, '✏️ 填入編輯器', function () {
      var csrfEl = document.querySelector('input[name="csrf_token"]');
      var csrfToken = csrfEl ? csrfEl.value : '';

      fetch('/actions/ai-fill-editor.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          csrf_token: csrfToken,
          trip_id: tripId,
          content: content,
        }),
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.ok && data.redirect) {
            window.location.href = data.redirect;
          } else {
            addMessage('❌ 填入失敗：' + (data.error || '未知錯誤'), 'system');
          }
        })
        .catch(function () {
          addMessage('❌ 填入失敗：網路錯誤', 'system');
        });
    });
  }

  /**
   * Show a "加入行程" button that calls fill_editor with the spot data
   */
  function addAddToTripButton(msgEl, spot) {
    addActionButton(msgEl, '📍 加入行程', function () {
      var tripId = state.currentTripId;
      if (!tripId) {
        addMessage('❌ 請先在編輯器中打開行程', 'system');
        return;
      }
      var csrfEl = document.querySelector('input[name="csrf_token"]');
      var csrfToken = csrfEl ? csrfEl.value : '';

      fetch('/actions/ai-fill-editor.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          csrf_token: csrfToken,
          trip_id: tripId,
          content: { spots: [spot] },
        }),
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.ok && data.redirect) {
            window.location.href = data.redirect;
          } else {
            addMessage('❌ 加入失敗：' + (data.error || '未知錯誤'), 'system');
          }
        })
        .catch(function () {
          addMessage('❌ 加入失敗：網路錯誤', 'system');
        });
    });
  }

  function addMessage(text, role, raw) {
    var div = document.createElement('div');
    div.className = 'tp-chat-msg ' + role;
    if (role === 'ai' && !raw) {
      div.innerHTML = renderMarkdown(text);
      div.querySelectorAll('a').forEach(function (a) { a.target = '_blank'; a.rel = 'noopener'; });
    } else {
      div.textContent = text;
    }
    msgContainer.appendChild(div);
    msgContainer.scrollTop = msgContainer.scrollHeight;
    state.messages.push({ text: text, role: role });
    return div;
  }

  function showTyping() {
    var el = document.createElement('div');
    el.className = 'tp-chat-typing';
    el.id = 'tp-chat-typing';
    el.innerHTML = '<span></span><span></span><span></span>';
    msgContainer.appendChild(el);
    msgContainer.scrollTop = msgContainer.scrollHeight;
  }

  function showToolStatus(text) {
    var el = document.createElement('div');
    el.className = 'tp-chat-msg system';
    el.id = 'tp-chat-tool-status';
    el.textContent = text;
    msgContainer.appendChild(el);
    msgContainer.scrollTop = msgContainer.scrollHeight;
  }

  function hideTyping() { 
    var el = document.getElementById('tp-chat-typing'); 
    if (el) el.remove(); 
    var st = document.getElementById('tp-chat-tool-status');
    if (st) st.remove();
  }
  function setInputEnabled(en) { inputEl.disabled = !en; sendBtn.disabled = !en; }

  // ───── Real API call ─────
  function getCsrfToken() {
    var el = document.querySelector('input[name="csrf_token"]');
    return el ? el.value : '';
  }

  function callChatApi(userText, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/actions/chat.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.timeout = 60000;

    xhr.onload = function () {
      if (xhr.status === 200) {
        try {
          var data = JSON.parse(xhr.responseText);
          callback(null, data);
        } catch (e) {
          callback(new Error('Invalid JSON response'));
        }
      } else {
        // API not available → fallback
        callback(new Error('API unavailable (' + xhr.status + ')'));
      }
    };

    xhr.onerror = function () { callback(new Error('Network error')); };
    xhr.ontimeout = function () { callback(new Error('Timeout')); };

    xhr.send(JSON.stringify({
      message: userText,
      csrf_token: getCsrfToken(),
      page: state.pageContext ? extractPageType(state.pageContext.url) : '',
      trip_data: state.currentTripId ? { trip_id: state.currentTripId } : null,
    }));
  }

  function extractPageType(url) {
    if (!url) return '';
    if (url.indexOf('/planner-dashboard') !== -1) return 'planner_dashboard';
    if (url.indexOf('/traveler-dashboard') !== -1) return 'traveler_dashboard';
    if (url.indexOf('/editor') !== -1) return 'editor';
    if (url.indexOf('/trip.php') !== -1 || url.indexOf('/trip?') !== -1) return 'trip';
    return 'home';
  }

  // ───── Demo reply (fallback when API is unavailable) ─────
  function demoReply(text) {
    var tripId = state.currentTripId;
    var lo = text.toLowerCase();

    // 填入編輯器 demo
    if (lo.indexOf('填入編輯器') !== -1 || lo.indexOf('填滿') !== -1 || lo.indexOf('fill editor') !== -1) {
      return {
        text: '好的！以下是為你生成的行程內容，可以點擊「填入編輯器」按鈕自動填寫表單 👇\n\n**行程標題：** 台北文青一日遊\n**行程摘要：** 探索台北最具特色的文青景點，從老城區到新創聚落，體驗台北的文化底蘊。',
        fillEditor: {
          trip_id: tripId || 1,
          content: {
            title: '台北文青一日遊',
            summary: '探索台北最具特色的文青景點，從老城區到新創聚落，體驗台北的文化底蘊。適合喜歡攝影、咖啡與手作文化的旅人。',
            spots: [
              { name: '大稻埕碼頭', address: '台北市大同區民生西路', notes: '傍晚看夕陽' },
              { name: '松山文創園區', address: '台北市信義區光復南路133號', notes: '常有展覽' },
            ],
          },
        },
      };
    }

    // 推薦景點 demo
    if (lo.indexOf('推薦景點') !== -1 || lo.indexOf('推薦') !== -1) {
      return {
        text: '以下是為你推薦的熱門景點：\n\n1. **九份老街** — 新北市瑞芳區\n2. **十分老街** — 新北市平溪區\n3. **猴硐貓村** — 新北市瑞芳區\n\n點擊「加入行程」可將景點加入目前的行程中。',
        recommendedSpots: [
          { name: '九份老街', address: '新北市瑞芳區基山街' },
          { name: '十分老街', address: '新北市平溪區十分街' },
          { name: '猴硐貓村', address: '新北市瑞芳區猴硐' },
        ],
      };
    }

    if (lo.indexOf('hello') !== -1 || lo.indexOf('hi') !== -1 || lo.indexOf('嗨') !== -1 || lo.indexOf('你好') !== -1)
      return { text: '你好！我是 Travel Platform 的 AI 助手。有什麼我可以幫你的嗎？試試說「填入編輯器」或「推薦景點」！' };
    if (lo.indexOf('功能') !== -1 || lo.indexOf('可以做') !== -1)
      return { text: '我目前可以：\n\n1. ✏️ **填入編輯器** — 幫你生成行程內容並自動填入表單\n2. 📍 **推薦景點** — 推薦熱門景點並加入行程\n3. 🔍 回答關於旅行平台的問題\n4. 💡 提供旅遊建議' };
    if (lo.indexOf('行程') !== -1 || lo.indexOf('trip') !== -1)
      return { text: '你可以在「探索行程」頁面瀏覽所有公開行程，或者在你的工作台管理自己的行程。需要我引導你到哪個頁面嗎？' };
    if (lo.indexOf('謝謝') !== -1 || lo.indexOf('謝') !== -1)
      return { text: '不客氣！有任何問題隨時問我 😊' };
    return { text: '感謝你的訊息！你可以試試問我：\n- 「填入編輯器」\n- 「推薦景點」\n- 「你有什麼功能？」' };
  }

  function handleApiResponse(userText) {
    callChatApi(userText, function (err, data) {
      hideTyping();
      setInputEnabled(true);
      inputEl.focus();

      if (err) {
        // Fall back to demo reply
        var demo = demoReply(userText);
        var msgEl = addMessage(demo.text, 'ai', false);

        // Show "填入編輯器" button
        if (demo.fillEditor) {
          addFillEditorButton(msgEl, demo.fillEditor.trip_id, demo.fillEditor.content);
        }

        // Show "加入行程" buttons for recommended spots
        if (demo.recommendedSpots && demo.recommendedSpots.length > 0) {
          for (var i = 0; i < demo.recommendedSpots.length; i++) {
            (function (spot) {
              var card = document.createElement('div');
              card.className = 'tp-chat-spot-card';
              card.innerHTML = '<div><div class="name">' + escapeHtml(spot.name) + '</div>' +
                (spot.address ? '<div class="addr">' + escapeHtml(spot.address) + '</div>' : '') + '</div>';
              msgEl.appendChild(card);
              addAddToTripButton(card, spot);
            })(demo.recommendedSpots[i]);
          }
        }
        return;
      }

      // Real API response — show reply text
      var replyText = data.reply || data.text || JSON.stringify(data);
      var msgEl = addMessage(replyText, 'ai', false);

      // Show tool calls used as system messages
      if (data.tool_calls && data.tool_calls.length > 0) {
        var toolNames = [];
        for (var k = 0; k < data.tool_calls.length; k++) {
          var tc = data.tool_calls[k];
          var label = tc.name || 'unknown';
          // Human-friendly labels
          if (label === 'search_trips') label = '搜尋行程';
          else if (label === 'get_trip_detail') label = '查看行程';
          else if (label === 'get_planner_stats') label = '統計資料';
          else if (label === 'get_traveler_footprints') label = '足跡資料';
          else if (label === 'recommend_trips') label = '推薦行程';
          else if (label === 'suggest_spots') label = '推薦景點';
          else if (label === 'generate_trip_summary') label = '生成摘要';
          else if (label === 'fill_editor') label = '填入編輯器';
          toolNames.push(label);
        }
        addMessage('🛠️ 已使用工具：' + toolNames.join('、'), 'system');
      }

      // Check for fill_editor action
      if (data._action === 'fill_editor' && data.trip_id && data.content) {
        addFillEditorButton(msgEl, data.trip_id, data.content);
      }

      // Check for recommended spots
      if (data._action === 'recommend_spots' && data.spots && data.spots.length > 0) {
        for (var j = 0; j < data.spots.length; j++) {
          (function (spot) {
            var card = document.createElement('div');
            card.className = 'tp-chat-spot-card';
            card.innerHTML = '<div><div class="name">' + escapeHtml(spot.name) + '</div>' +
              (spot.address ? '<div class="addr">' + escapeHtml(spot.address) + '</div>' : '') + '</div>';
            msgEl.appendChild(card);
            addAddToTripButton(card, spot);
          })(data.spots[j]);
        }
      }
    });
  }

  function sendMessage() {
    var text = inputEl.value.trim();
    if (!text) return;

    var ctx = state.pageContext;
    if (ctx && ctx.url && ctx.url !== 'about:blank') {
      var hasCtx = state.messages.some(function (m) { return m.role === 'system' && m.text.indexOf('頁面資訊') !== -1; });
      if (!hasCtx) {
        addMessage('📄 頁面資訊：' + ctx.title + ' — ' + ctx.url, 'system');
        state.pageContext = null;
      }
    }

    inputEl.value = '';
    addMessage(text, 'user', true);

    showTyping();
    showToolStatus('🔍 正在查詢中...');
    setInputEnabled(false);
    handleApiResponse(text);
  }

  function openPanel() {
    if (state.open) return;
    state.open = true;
    panel.classList.add('open');
    bubble.style.display = 'none';
    inputEl.focus();
    msgContainer.scrollTop = msgContainer.scrollHeight;
  }

  function closePanel() {
    if (!state.open) return;
    state.open = false;
    panel.classList.remove('open');
    bubble.style.display = 'flex';
  }

  function initResize() {
    var sx, sy, sw, sh;
    function start(e) { e.preventDefault(); sx = e.clientX; sy = e.clientY; sw = panel.offsetWidth; sh = panel.offsetHeight; document.addEventListener('mousemove', move); document.addEventListener('mouseup', end); }
    function move(e) { var w = Math.max(260, sw + (e.clientX - sx)); var h = Math.max(300, sh - (e.clientY - sy)); panel.style.width = w + 'px'; panel.style.height = h + 'px'; }
    function end() { document.removeEventListener('mousemove', move); document.removeEventListener('mouseup', end); }
    resizeHandle.addEventListener('mousedown', start);
  }

  function bindEvents() {
    bubble.addEventListener('click', openPanel);
    closeBtn.addEventListener('click', closePanel);
    sendBtn.addEventListener('click', sendMessage);
    inputEl.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } });
  }

  function init() {
    injectCSS();
    buildDOM();
    bindEvents();
    initResize();
    addMessage('👋 你好！我是 Travel Platform 的 AI 助手，有什麼可以幫忙的嗎？', 'ai', false);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
