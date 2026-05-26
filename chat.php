<?php
require_once __DIR__ . "/includes/helpers.php";
require_once __DIR__ . "/includes/group_context.php";

$uid = (int)$_SESSION["user_id"];
$joined = (($_GET["joined"] ?? "") === "1");

$stmt = $conn->prepare("SELECT vark_style FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $uid);
$stmt->execute();
$userVark = $stmt->get_result()->fetch_assoc()["vark_style"] ?? "R";

if (empty($_SESSION["csrf_token"])) {
  $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["csrf_token"];

$activeTab = "chat";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <title>CSH - <?= htmlspecialchars($group["name"]) ?> | Chat</title>
  <link rel="stylesheet" href="assets/css/theme.css">
  <style>
    .chat-header-info { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 8px; }
    .input-container { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 10px; }
    .input-container textarea { flex: 1; min-height: 46px; max-height: 120px; }
    .action-btn { padding: 10px 14px; border-radius: 40px; background: rgba(255,255,255,0.06); border: 1px solid var(--border); cursor: pointer; transition: all 0.2s; }
    .action-btn:hover { background: rgba(110,231,255,0.15); transform: translateY(-1px); }
    .image-preview { margin-top: 5px; max-width: 100px; border-radius: 8px; display: none; }
    .image-preview img { max-width: 80px; border-radius: 8px; }
    .recording-timer { font-size: 12px; color: #fb7185; margin-left: 10px; }
    
    .chat-box { 
      flex: 1; 
      overflow-y: auto; 
      padding: 16px; 
      border-radius: 16px; 
      border: 1px solid var(--border); 
      background: rgba(0,0,0,0.2);
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .msg { display: flex; margin: 0; gap: 10px; align-items: flex-end; }
    .msg.me { justify-content: flex-end; }
    .bubble { 
      max-width: 70%; 
      padding: 10px 14px; 
      border-radius: 18px; 
      border: 1px solid var(--border); 
      background: rgba(255,255,255,0.06);
      word-break: break-word;
    }
    .msg.me .bubble { 
      border-color: rgba(110,231,255,0.4); 
      background: linear-gradient(135deg, rgba(110,231,255,0.15), rgba(167,139,250,0.1));
    }
    .meta { font-size: 11px; color: rgba(255,255,255,0.4); margin-top: 6px; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
    
    .chat-img { max-width: 200px; max-height: 150px; border-radius: 12px; margin-bottom: 6px; cursor: pointer; display: block; }
    .chat-audio { max-width: 250px; border-radius: 30px; margin-bottom: 6px; display: block; }
    
    .media-selector { margin-bottom: 12px; }
    .media-buttons {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      background: rgba(17, 31, 56, 0.5);
      padding: 8px;
      border-radius: 60px;
      backdrop-filter: blur(4px);
    }
    .media-btn {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 8px 20px;
      border-radius: 40px;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.1);
      color: var(--text-muted);
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .media-btn:hover { background: rgba(110,231,255,0.15); border-color: rgba(110,231,255,0.4); transform: translateY(-1px); }
    .media-btn.active {
      background: linear-gradient(135deg, rgba(110,231,255,0.2), rgba(167,139,250,0.15));
      border-color: rgba(110,231,255,0.5);
      color: var(--brand-cyan);
    }
    .media-icon { font-size: 18px; }
    .media-label { font-size: 14px; }
    
    .delete-msg-btn {
      background: none;
      border: none;
      color: #fb7185;
      cursor: pointer;
      font-size: 14px;
      opacity: 0.6;
    }
    .delete-msg-btn:hover { opacity: 1; }
    
    @media (max-width: 500px) {
      .media-btn { padding: 6px 14px; }
      .media-label { display: none; }
      .media-icon { font-size: 20px; }
    }

    .whiteboard-modal {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.85);
      z-index: 999;
      display: none;
      align-items: center;
      justify-content: center;
      backdrop-filter: blur(5px);
    }
    
    .topbar {
      z-index: 1000 !important;
      position: sticky;
    }
    
    .whiteboard-container {
      background: #1a1a2e;
      border-radius: 24px;
      padding: 20px;
      width: 95%;
      max-width: 1100px;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 20px 60px rgba(0,0,0,0.5);
      position: relative;
      z-index: 1001;
    }
    
    body.modal-open {
      overflow: hidden;
    }
    
    .whiteboard-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      color: white;
    }
    .whiteboard-header h3 { margin: 0; font-size: 20px; }
    .canvas-wrapper {
      background: white;
      border-radius: 16px;
      padding: 10px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    .whiteboard-canvas {
      display: block;
      width: 100%;
      height: auto;
      background: white;
      border-radius: 8px;
      cursor: crosshair;
      touch-action: none;
    }
    .whiteboard-tools {
      display: flex;
      gap: 12px;
      margin-top: 15px;
      flex-wrap: wrap;
      justify-content: center;
      align-items: center;
    }
    .tool-group {
      display: flex;
      gap: 8px;
      background: rgba(255,255,255,0.1);
      padding: 8px 12px;
      border-radius: 50px;
      flex-wrap: wrap;
      justify-content: center;
    }
    .color-btn {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      border: 2px solid white;
      cursor: pointer;
      transition: transform 0.2s;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    .color-btn:hover { transform: scale(1.1); }
    .color-btn.active { border: 3px solid var(--brand-cyan); transform: scale(1.1); }
    .tool-btn {
      background: rgba(255,255,255,0.15);
      border: none;
      padding: 8px 16px;
      border-radius: 40px;
      color: white;
      cursor: pointer;
      font-size: 14px;
      transition: all 0.2s;
    }
    .tool-btn:hover { background: rgba(110,231,255,0.3); transform: translateY(-1px); }
    .tool-btn.active { background: rgba(110,231,255,0.4); color: var(--brand-cyan); }
    .brush-size {
      display: flex;
      align-items: center;
      gap: 10px;
      background: rgba(255,255,255,0.1);
      padding: 5px 15px;
      border-radius: 40px;
    }
    .brush-size input { width: 100px; cursor: pointer; }
    .brush-size span { color: white; font-size: 12px; }
    @media (max-width: 700px) {
      .whiteboard-tools { gap: 8px; }
      .tool-group { padding: 6px 10px; }
      .color-btn { width: 32px; height: 32px; }
      .tool-btn { padding: 6px 12px; font-size: 12px; }
    }
  </style>
</head>
<body>
  <?php include __DIR__ . "/includes/navbar.php"; ?>

  <div class="container">
    <div class="card">
      <div class="hd">
        <div>
          <h1 class="h1"><?= htmlspecialchars($group["name"]) ?></h1>
          <div class="chat-header-info">
            <span class="badge">🔑 <?= htmlspecialchars($group["group_code"]) ?></span>
            <span class="badge">📖 <?= htmlspecialchars($group["subject"]) ?></span>
            <span class="badge <?= vark_badge_class($group["vark_type"]) ?>">
              <?= vark_icon($group["vark_type"]) ?> <?= htmlspecialchars($group["vark_type"]) ?>
            </span>
          </div>
          <?php include __DIR__ . "/includes/group_tabs.php"; ?>
        </div>
        <a class="btn" href="group.php?code=<?= urlencode($group["group_code"]) ?>">📋 Info</a>
      </div>

      <div class="bd">
        <?php if ($joined): ?>
          <div class="notice good">✅ Joined successfully!</div>
        <?php endif; ?>

        <div class="chat-wrap">
          <div id="chatBox" class="chat-box"></div>

          <div class="media-selector">
            <div class="media-buttons">
              <button type="button" class="media-btn active" data-media="text"><span class="media-icon">📝</span><span class="media-label">Text</span></button>
              <button type="button" class="media-btn" data-media="image"><span class="media-icon">🖼️</span><span class="media-label">Image</span></button>
              <button type="button" class="media-btn" data-media="audio"><span class="media-icon">🎵</span><span class="media-label">Voice</span></button>
              <button type="button" class="media-btn" data-media="whiteboard"><span class="media-icon">🎨</span><span class="media-label">Whiteboard</span></button>
            </div>
          </div>

          <div id="inputText" class="input-container">
            <textarea id="textMessage" class="textarea" placeholder="Type your message... (Enter to send)"></textarea>
            <button id="sendTextBtn" class="btn btn-primary">Send</button>
          </div>

          <div id="inputImage" class="input-container" style="display: none;">
            <textarea id="imageMessage" class="textarea" placeholder="Add a caption..."></textarea>
            <label class="action-btn" style="cursor:pointer;">📷 Choose Image<input type="file" id="imageFile" accept="image/*" style="display:none;"></label>
            <button id="sendImageBtn" class="btn btn-primary">Send</button>
            <div id="imagePreviewArea" class="image-preview"></div>
          </div>

          <div id="inputAudio" class="input-container" style="display: none;">
            <textarea id="audioMessage" class="textarea" placeholder="Add a note..."></textarea>
            <button id="recordAudioBtn" class="action-btn">🎙️ Record</button>
            <button id="sendAudioBtn" class="btn btn-primary" disabled>Send</button>
            <span id="audioStatus" class="recording-timer"></span>
          </div>

          <div id="inputWhiteboard" class="input-container" style="display: none;">
            <textarea id="whiteboardMessage" class="textarea" placeholder="Describe your drawing..."></textarea>
            <button id="openWhiteboardBtn" class="action-btn" style="background: rgba(110,231,255,0.15);">🎨 Open Whiteboard</button>
            <button id="sendWhiteboardBtn" class="btn btn-primary">Send</button>
          </div>
          
          <div class="p" style="margin: 8px 0 0; font-size: 12px;">💡 Enter = send, Shift+Enter = new line</div>
        </div>
      </div>
    </div>
  </div>

  <div id="whiteboardModal" class="whiteboard-modal">
    <div class="whiteboard-container">
      <div class="whiteboard-header">
        <h3>🎨 Whiteboard Studio</h3>
        <button id="closeWhiteboardBtn" style="background: none; border: none; color: white; font-size: 32px; cursor: pointer; padding: 0 10px;">&times;</button>
      </div>
      
      <div class="canvas-wrapper">
        <canvas id="whiteboardCanvas" width="1000" height="550" class="whiteboard-canvas" style="width:100%; height:auto; max-width:1000px; background:white; border-radius:12px;"></canvas>
      </div>
      
      <div class="whiteboard-tools">
        <div class="tool-group">
          <button id="colorBlack" class="color-btn" style="background:#000000;" title="Black"></button>
          <button id="colorRed" class="color-btn" style="background:#FF0000;" title="Red"></button>
          <button id="colorBlue" class="color-btn" style="background:#0066FF;" title="Blue"></button>
          <button id="colorGreen" class="color-btn" style="background:#00CC66;" title="Green"></button>
          <button id="colorYellow" class="color-btn" style="background:#FFCC00;" title="Yellow"></button>
          <button id="colorPurple" class="color-btn" style="background:#9933FF;" title="Purple"></button>
          <button id="colorOrange" class="color-btn" style="background:#FF6600;" title="Orange"></button>
          <button id="colorPink" class="color-btn" style="background:#FF6699;" title="Pink"></button>
        </div>
        
        <div class="tool-group">
          <button id="toolPen" class="tool-btn active">✏️ Pen</button>
          <button id="toolEraser" class="tool-btn">🧽 Eraser</button>
        </div>
        
        <div class="brush-size">
          <span>✏️ Size:</span>
          <input type="range" id="brushSize" min="1" max="30" value="3">
          <span id="sizeValue">3px</span>
        </div>
        
        <div class="tool-group">
          <button id="undoBtn" class="tool-btn">↩️ Undo</button>
          <button id="redoBtn" class="tool-btn">↪️ Redo</button>
          <button id="clearCanvasBtn" class="tool-btn" style="color:#fb7185;">🗑️ Clear</button>
          <button id="saveWhiteboardBtn" class="tool-btn" style="background: rgba(52,211,153,0.3);">📸 Send</button>
        </div>
      </div>
    </div>
  </div>

<script>

const GROUP_CODE = <?= json_encode($group["group_code"]) ?>;
const MY_ID = <?= json_encode($uid) ?>;
const CSRF = <?= json_encode($csrf) ?>;

let lastId = 0;
let isSending = false;
let currentMediaType = 'text';
let currentImageData = null;
let currentAudioData = null;
let currentWhiteboardData = null;
let mediaRecorder = null;
let audioChunks = [];
let isRecording = false;

// DOM Elements
const chatBox = document.getElementById('chatBox');
const inputText = document.getElementById('inputText');
const inputImage = document.getElementById('inputImage');
const inputAudio = document.getElementById('inputAudio');
const inputWhiteboard = document.getElementById('inputWhiteboard');

function esc(s) {
  if (!s) return '';
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function atBottom() {
  return (chatBox.scrollTop + chatBox.clientHeight) >= (chatBox.scrollHeight - 50);
}

function scrollToBottom() {
  chatBox.scrollTop = chatBox.scrollHeight;
}

function renderMessage(m) {
  const mine = (Number(m.user_id) === Number(MY_ID));
  const msgDiv = document.createElement('div');
  msgDiv.className = 'msg' + (mine ? ' me' : '');
  msgDiv.setAttribute('data-msg-id', m.id);
  
  const bubble = document.createElement('div');
  bubble.className = 'bubble';
  
  let content = '';
  if (m.image_data && m.image_data !== 'null') {
    content += `<img class="chat-img" src="${m.image_data}" onclick="window.open(this.src)" alt="image">`;
    if (m.message_text) content += `<br>`;
  }
  if (m.audio_data && m.audio_data !== 'null') {
    content += `<audio class="chat-audio" controls src="${m.audio_data}"></audio>`;
    if (m.message_text) content += `<br>`;
  }
  if (m.whiteboard_data && m.whiteboard_data !== 'null') {
    content += `<img class="chat-img" src="${m.whiteboard_data}" onclick="window.open(this.src)" alt="whiteboard">`;
    if (m.message_text) content += `<br>`;
  }
  if (m.message_text) {
    content += `<span class="message-text">${esc(m.message_text).replace(/\n/g, '<br>')}</span>`;
  }
  if (!content) content = '(empty message)';
  
  bubble.innerHTML = content;
  
  const meta = document.createElement('div');
  meta.className = 'meta';
  const timeSpan = document.createElement('span');
  timeSpan.textContent = (mine ? 'You' : m.user_name) + ' • ' + m.created_at;
  meta.appendChild(timeSpan);
  
  if (m.can_delete === true || m.can_delete === 1) {
    const deleteBtn = document.createElement('button');
    deleteBtn.innerHTML = '🗑️';
    deleteBtn.className = 'delete-msg-btn';
    deleteBtn.title = 'Delete';
    deleteBtn.onclick = (function(msgId) { return function() { deleteMessage(msgId); }; })(m.id);
    meta.appendChild(deleteBtn);
  }
  
  bubble.appendChild(meta);
  msgDiv.appendChild(bubble);
  return msgDiv;
}

async function deleteMessage(messageId) {
  if (!confirm('Delete this message?')) return;
  try {
    const form = new FormData();
    form.append('code', GROUP_CODE);
    form.append('message_id', messageId);
    form.append('csrf_token', CSRF);
    const res = await fetch('chat_delete.php', { method: 'POST', body: form });
    const data = await res.json();
    if (data && data.ok) {
      const el = document.querySelector(`.msg[data-msg-id="${messageId}"]`);
      if (el) el.remove();
    }
  } catch(err) {}
}

async function fetchMessages(initial = false) {
  const wasBottom = atBottom();
  const url = `chat_fetch.php?code=${encodeURIComponent(GROUP_CODE)}&after_id=${lastId}`;
  try {
    const res = await fetch(url, { credentials: 'same-origin' });
    const data = await res.json();
    if (!data || !data.ok) return;
    if (initial) chatBox.innerHTML = '';
    if (data.messages && data.messages.length) {
      for (const m of data.messages) {
        chatBox.appendChild(renderMessage(m));
        lastId = Math.max(lastId, Number(m.id));
      }
      if (wasBottom || initial) scrollToBottom();
    } else if (initial) {
      chatBox.innerHTML = '<div class="notice">💬 No messages yet.</div>';
    }
  } catch(err) {}
}

async function sendTextMessage(text) {
  if (!text.trim() || isSending) return false;
  isSending = true;
  try {
    const form = new FormData();
    form.append('code', GROUP_CODE);
    form.append('message', text);
    form.append('csrf_token', CSRF);
    const res = await fetch('chat_send.php', { method: 'POST', body: form });
    const data = await res.json();
    if (data && data.ok) { await fetchMessages(false); return true; }
    return false;
  } catch(err) { return false; } finally { isSending = false; }
}

async function sendMediaMessage(text, mediaType, mediaData) {
  if (isSending) return false;
  isSending = true;
  try {
    const form = new FormData();
    form.append('code', GROUP_CODE);
    form.append('message', text);
    form.append('media_type', mediaType);
    form.append('media_data', mediaData);
    form.append('csrf_token', CSRF);
    const res = await fetch('chat_send_media.php', { method: 'POST', body: form });
    const data = await res.json();
    if (data && data.ok) { await fetchMessages(false); return true; }
    else { alert(data?.error || 'Failed'); return false; }
  } catch(err) { alert('Network error'); return false; } finally { isSending = false; }
}

document.querySelectorAll('.media-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.media-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentMediaType = btn.getAttribute('data-media');
    inputText.style.display = 'none';
    inputImage.style.display = 'none';
    inputAudio.style.display = 'none';
    inputWhiteboard.style.display = 'none';
    if (currentMediaType === 'text') inputText.style.display = 'flex';
    else if (currentMediaType === 'image') inputImage.style.display = 'flex';
    else if (currentMediaType === 'audio') inputAudio.style.display = 'flex';
    else if (currentMediaType === 'whiteboard') inputWhiteboard.style.display = 'flex';
  });
});

const textMsg = document.getElementById('textMessage');
const sendText = document.getElementById('sendTextBtn');
sendText.addEventListener('click', async () => {
  const txt = textMsg.value.trim();
  if (txt && await sendTextMessage(txt)) textMsg.value = '';
});
textMsg.addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendText.click(); }
});

const imageMsg = document.getElementById('imageMessage');
const imageFile = document.getElementById('imageFile');
const sendImage = document.getElementById('sendImageBtn');
const imagePreview = document.getElementById('imagePreviewArea');

document.querySelector('#inputImage .action-btn')?.addEventListener('click', () => imageFile.click());

imageFile.addEventListener('change', (e) => {
  const file = e.target.files[0];
  if (file && file.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = (ev) => {
      currentImageData = ev.target.result;
      imagePreview.innerHTML = `<img src="${currentImageData}" style="max-width:80px;"> <button onclick="currentImageData=null; this.parentElement.style.display='none';" style="background:none; border:none; color:#fb7185;">✖️</button>`;
      imagePreview.style.display = 'block';
    };
    reader.readAsDataURL(file);
  }
});

sendImage.addEventListener('click', async () => {
  const txt = imageMsg.value.trim();
  if (currentImageData) {
    if (await sendMediaMessage(txt, 'image', currentImageData)) {
      imageMsg.value = '';
      currentImageData = null;
      imagePreview.style.display = 'none';
      imageFile.value = '';
    }
  } else if (txt && await sendTextMessage(txt)) imageMsg.value = '';
});

const audioMsg = document.getElementById('audioMessage');
const recordBtn = document.getElementById('recordAudioBtn');
const sendAudio = document.getElementById('sendAudioBtn');
const audioStatus = document.getElementById('audioStatus');

recordBtn.addEventListener('click', async () => {
  if (isRecording && mediaRecorder) {
    mediaRecorder.stop();
    isRecording = false;
    recordBtn.textContent = '🎙️ Record';
    recordBtn.style.background = '';
    audioStatus.textContent = 'Processing...';
    return;
  }
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    mediaRecorder = new MediaRecorder(stream);
    audioChunks = [];
    mediaRecorder.ondataavailable = (e) => audioChunks.push(e.data);
    mediaRecorder.onstop = () => {
      const blob = new Blob(audioChunks, { type: 'audio/webm' });
      const reader = new FileReader();
      reader.onload = () => {
        currentAudioData = reader.result;
        audioStatus.textContent = '✅ Ready!';
        sendAudio.disabled = false;
      };
      reader.readAsDataURL(blob);
      stream.getTracks().forEach(t => t.stop());
    };
    mediaRecorder.start();
    isRecording = true;
    recordBtn.textContent = '⏹️ Stop';
    recordBtn.style.background = 'rgba(251,113,133,0.2)';
    audioStatus.textContent = '🔴 Recording...';
    sendAudio.disabled = true;
  } catch(err) { alert('Microphone access denied.'); }
});

sendAudio.addEventListener('click', async () => {
  const txt = audioMsg.value.trim();
  if (currentAudioData) {
    if (await sendMediaMessage(txt, 'audio', currentAudioData)) {
      audioMsg.value = '';
      currentAudioData = null;
      audioStatus.textContent = '';
      sendAudio.disabled = true;
    }
  }
});

const whiteMsg = document.getElementById('whiteboardMessage');
const openBoard = document.getElementById('openWhiteboardBtn');
const sendBoard = document.getElementById('sendWhiteboardBtn');
const modal = document.getElementById('whiteboardModal');
const canvas = document.getElementById('whiteboardCanvas');
const closeBoard = document.getElementById('closeWhiteboardBtn');
const clearBtn = document.getElementById('clearCanvasBtn');
const saveBtn = document.getElementById('saveWhiteboardBtn');
const undoBtn = document.getElementById('undoBtn');
const redoBtn = document.getElementById('redoBtn');
const brushSizeSlider = document.getElementById('brushSize');
const sizeValue = document.getElementById('sizeValue');
const toolPen = document.getElementById('toolPen');
const toolEraser = document.getElementById('toolEraser');

let ctx = canvas.getContext('2d');
let drawing = false;
let currentColor = '#000000';
let currentBrushSize = 3;
let isEraser = false;
let history = [];
let historyStep = -1;

function saveState() {
  history = history.slice(0, historyStep + 1);
  history.push(canvas.toDataURL());
  historyStep++;
}

function undo() {
  if (historyStep > 0) {
    historyStep--;
    const img = new Image();
    img.onload = () => {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(img, 0, 0);
    };
    img.src = history[historyStep];
  }
}

function redo() {
  if (historyStep < history.length - 1) {
    historyStep++;
    const img = new Image();
    img.onload = () => {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(img, 0, 0);
    };
    img.src = history[historyStep];
  }
}

function initCanvas() {
  ctx.fillStyle = '#FFFFFF';
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  ctx.strokeStyle = currentColor;
  ctx.lineWidth = currentBrushSize;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  saveState();
}

function getCoordinates(e) {
  const rect = canvas.getBoundingClientRect();
  const scaleX = canvas.width / rect.width;
  const scaleY = canvas.height / rect.height;
  let clientX, clientY;
  if (e.touches) {
    clientX = e.touches[0].clientX;
    clientY = e.touches[0].clientY;
  } else {
    clientX = e.clientX;
    clientY = e.clientY;
  }
  let x = (clientX - rect.left) * scaleX;
  let y = (clientY - rect.top) * scaleY;
  x = Math.max(0, Math.min(canvas.width, x));
  y = Math.max(0, Math.min(canvas.height, y));
  return { x, y };
}

function startDraw(e) {
  drawing = true;
  const pos = getCoordinates(e);
  ctx.beginPath();
  ctx.moveTo(pos.x, pos.y);
  ctx.lineTo(pos.x, pos.y);
  ctx.stroke();
  e.preventDefault();
}

function draw(e) {
  if (!drawing) return;
  e.preventDefault();
  const pos = getCoordinates(e);
  ctx.lineTo(pos.x, pos.y);
  ctx.stroke();
  ctx.beginPath();
  ctx.moveTo(pos.x, pos.y);
}

function endDraw() {
  if (drawing) {
    drawing = false;
    ctx.beginPath();
    saveState();
  }
}

function updateBrush() {
  if (isEraser) {
    ctx.strokeStyle = '#FFFFFF';
  } else {
    ctx.strokeStyle = currentColor;
  }
  ctx.lineWidth = currentBrushSize;
  brushSizeSlider.value = currentBrushSize;
  sizeValue.textContent = currentBrushSize + 'px';
}

brushSizeSlider.addEventListener('input', (e) => {
  currentBrushSize = parseInt(e.target.value);
  updateBrush();
});

toolPen.addEventListener('click', () => {
  isEraser = false;
  toolPen.classList.add('active');
  toolEraser.classList.remove('active');
  updateBrush();
});

toolEraser.addEventListener('click', () => {
  isEraser = true;
  toolEraser.classList.add('active');
  toolPen.classList.remove('active');
  updateBrush();
});

// Color selection
const colors = ['#000000', '#FF0000', '#0066FF', '#00CC66', '#FFCC00', '#9933FF', '#FF6600', '#FF6699'];
const colorBtns = [
  document.getElementById('colorBlack'), document.getElementById('colorRed'),
  document.getElementById('colorBlue'), document.getElementById('colorGreen'),
  document.getElementById('colorYellow'), document.getElementById('colorPurple'),
  document.getElementById('colorOrange'), document.getElementById('colorPink')
];

colorBtns.forEach((btn, idx) => {
  if (btn) {
    btn.addEventListener('click', () => {
      currentColor = colors[idx];
      isEraser = false;
      toolPen.classList.add('active');
      toolEraser.classList.remove('active');
      updateBrush();
      colorBtns.forEach(b => b?.classList.remove('active'));
      btn.classList.add('active');
    });
  }
});

// Canvas events
canvas.addEventListener('mousedown', startDraw);
canvas.addEventListener('mousemove', draw);
canvas.addEventListener('mouseup', endDraw);
canvas.addEventListener('mouseleave', endDraw);
canvas.addEventListener('touchstart', startDraw);
canvas.addEventListener('touchmove', draw);
canvas.addEventListener('touchend', endDraw);

undoBtn.addEventListener('click', undo);
redoBtn.addEventListener('click', redo);
clearBtn.addEventListener('click', () => {
  if (confirm('Clear entire whiteboard?')) {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#FFFFFF';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    saveState();
  }
});

function openWhiteboardModal() {
  modal.style.display = 'flex';
  document.body.classList.add('modal-open');
  setTimeout(() => initCanvas(), 100);
}

function closeWhiteboardModal() {
  modal.style.display = 'none';
  document.body.classList.remove('modal-open');
}

openBoard.addEventListener('click', openWhiteboardModal);
closeBoard.addEventListener('click', closeWhiteboardModal);

modal.addEventListener('click', (e) => {
  if (e.target === modal) closeWhiteboardModal();
});

// Escape key
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && modal.style.display === 'flex') closeWhiteboardModal();
});

saveBtn.addEventListener('click', async () => {
  currentWhiteboardData = canvas.toDataURL('image/png');
  const txt = whiteMsg.value.trim();
  if (await sendMediaMessage(txt, 'whiteboard', currentWhiteboardData)) {
    whiteMsg.value = '';
    closeWhiteboardModal();
  }
});

sendBoard.addEventListener('click', async () => {
  if (currentWhiteboardData) {
    const txt = whiteMsg.value.trim();
    if (await sendMediaMessage(txt, 'whiteboard', currentWhiteboardData)) {
      whiteMsg.value = '';
      currentWhiteboardData = null;
    }
  } else {
    alert('Please draw something on the whiteboard first.');
  }
});

fetchMessages(true);
setInterval(() => fetchMessages(false), 3000);
</script>
</body>
</html>