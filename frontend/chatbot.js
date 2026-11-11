
(function () {
  'use strict';

  // Tránh khởi tạo chatbot nhiều lần
  if (window.pharmaChatbotLoaded) return;
  window.pharmaChatbotLoaded = true;

  const styles = `
    :root {
      --ph-primary: #0099CF;
      --ph-dark: #101732;
      --ph-blue: #1040C5;
      --ph-secondary: #0D74B1;
      --ph-gray: #B8ACAA;
      --ph-white: #FFFFFF;
    }

    .ph-chat-button {
      position: fixed;
      right: 24px;
      bottom: 24px;
      width: 58px;
      height: 58px;
      border: 0;
      border-radius: 50%;
      background: var(--ph-primary);
      color: white;
      font-size: 25px;
      cursor: pointer;
      box-shadow: 0 5px 18px rgba(16,23,50,.25);
      z-index: 9998;
      transition: .2s;
    }

    .ph-chat-button:hover {
      background: var(--ph-secondary);
      transform: scale(1.05);
    }

    .ph-chat-window {
      position: fixed;
      right: 24px;
      bottom: 94px;
      width: 350px;
      max-width: calc(100vw - 32px);
      height: 470px;
      max-height: calc(100vh - 125px);
      background: var(--ph-white);
      border: 1px solid #dceaf0;
      border-radius: 16px;
      box-shadow: 0 8px 30px rgba(16,23,50,.18);
      display: none;
      flex-direction: column;
      overflow: hidden;
      z-index: 9999;
      font-family: Arial, sans-serif;
    }

    .ph-chat-window.open {
      display: flex;
    }

    .ph-chat-header {
      background: var(--ph-primary);
      color: white;
      padding: 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .ph-chat-header strong {
      font-size: 15px;
      color: white;
    }

    .ph-chat-header small {
      display: block;
      margin-top: 4px;
      opacity: .9;
      font-size: 11px;
    }

    .ph-chat-close {
      border: 0;
      background: transparent;
      color: white;
      font-size: 23px;
      cursor: pointer;
    }

    .ph-chat-body {
      flex: 1;
      padding: 14px;
      overflow-y: auto;
      background: #F2FAFD;
    }

    .ph-chat-message {
      width: fit-content;
      max-width: 85%;
      padding: 10px 12px;
      margin-bottom: 10px;
      border-radius: 12px;
      font-size: 13px;
      line-height: 1.5;
      white-space: pre-wrap;
      overflow-wrap: anywhere;
    }

    /* Tin nhắn chatbot */
/* Tin nhắn chatbot */
.ph-chat-message.bot {
    background: var(--ph-white);
    color: var(--ph-dark);
    border: 1px solid #e0edf2;
    border-bottom-left-radius: 3px;
    /* Căn giữa ngang và dọc */
    display: grid;
    place-items: center;
    text-align: center;
    /* Chiều cao tối thiểu */
    min-height: 80px;
    box-sizing: border-box;
}
    /* Tin nhắn người dùng */
    .ph-chat-message.user {
      margin-left: auto;
      background: var(--ph-blue);
      color: white;
      border-bottom-right-radius: 3px;
    }

    /* Câu hỏi gợi ý */
    .ph-chat-suggestions {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 10px;
    }

    .ph-chat-suggestions button {
      border: 1px solid #a9dced;
      background: white;
      color: var(--ph-secondary);
      border-radius: 20px;
      padding: 6px 9px;
      font-size: 11px;
      cursor: pointer;
      transition: .2s;
    }

    .ph-chat-suggestions button:hover {
      background: var(--ph-primary);
      color: white;
      border-color: var(--ph-primary);
    }

    /* Khung nhập tin nhắn */
    .ph-chat-footer {
      display: flex;
      gap: 8px;
      padding: 12px;
      border-top: 1px solid #e5edf2;
      background: white;
    }

    .ph-chat-input {
      flex: 1;
      min-width: 0;
      border: 1px solid #dce2e8;
      border-radius: 20px;
      padding: 9px 13px;
      outline: none;
      font-size: 13px;
      color: var(--ph-dark);
    }

    .ph-chat-input:focus {
      border-color: var(--ph-primary);
    }

    .ph-chat-send {
      border: 0;
      border-radius: 50%;
      width: 38px;
      height: 38px;
      background: var(--ph-primary);
      color: white;
      cursor: pointer;
      transition: .2s;
    }

    .ph-chat-send:hover {
      background: var(--ph-blue);
    }

    @media (max-width: 480px) {
      .ph-chat-window {
        right: 12px;
        bottom: 82px;
        width: calc(100vw - 24px);
      }

      .ph-chat-button {
        right: 16px;
        bottom: 16px;
      }
    }
  `;

  // Thêm CSS vào trang
  const styleElement = document.createElement('style');
  styleElement.textContent = styles;
  document.head.appendChild(styleElement);

  // Tạo giao diện chatbot
  const wrapper = document.createElement('div');

  wrapper.innerHTML = `
    <button class="ph-chat-button" type="button"
      aria-label="Mở trợ lý PharmaPro" aria-expanded="false">
      💬
    </button>

    <section class="ph-chat-window" aria-label="Trợ lý PharmaPro">

      <header class="ph-chat-header">
        <div>
          <strong>Trợ lý PharmaPro</strong>
          <small>Trợ lý hỗ trợ quản lý nhà thuốc</small>
        </div>

        <button class="ph-chat-close" type="button"
          aria-label="Đóng cửa sổ chat">&times;</button>
      </header>

      <div class="ph-chat-body" aria-live="polite">

        <div class="ph-chat-message bot">
          Xin chào! Mình là trợ lý PharmaPro.
          Mình có thể hỗ trợ bạn tìm hiểu các chức năng quản lý nhà thuốc.
        </div>

        <div class="ph-chat-suggestions">
          <button type="button"
            data-question="Hướng dẫn quản lý sản phẩm">
            Quản lý sản phẩm
          </button>

          <button type="button"
            data-question="Hướng dẫn kiểm tra tồn kho">
            Kiểm tra tồn kho
          </button>

          <button type="button"
            data-question="Hướng dẫn quản lý đơn hàng">
            Quản lý đơn hàng
          </button>

          <button type="button"
            data-question="Hướng dẫn quản lý khách hàng">
            Quản lý khách hàng
          </button>
        </div>

      </div>

      <form class="ph-chat-footer">
        <input class="ph-chat-input" type="text"
          placeholder="Nhập câu hỏi của bạn..."
          autocomplete="off"
          aria-label="Nhập tin nhắn">

        <button class="ph-chat-send" type="submit"
          aria-label="Gửi tin nhắn">➤</button>
      </form>

    </section>
  `;

  document.body.appendChild(wrapper);

  // Lấy các phần tử
  const toggleButton = wrapper.querySelector('.ph-chat-button');
  const chatWindow = wrapper.querySelector('.ph-chat-window');
  const closeButton = wrapper.querySelector('.ph-chat-close');
  const chatBody = wrapper.querySelector('.ph-chat-body');
  const chatForm = wrapper.querySelector('.ph-chat-footer');
  const chatInput = wrapper.querySelector('.ph-chat-input');

  // Mở chatbot
  function openChat() {
    chatWindow.classList.add('open');
    toggleButton.setAttribute('aria-expanded', 'true');
    chatInput.focus();
  }

  // Đóng chatbot
  function closeChat() {
    chatWindow.classList.remove('open');
    toggleButton.setAttribute('aria-expanded', 'false');
  }

  // Thêm tin nhắn
  function addMessage(content, sender) {
    const message = document.createElement('div');

    message.className = `ph-chat-message ${sender}`;
    message.textContent = content;

    chatBody.appendChild(message);
    chatBody.scrollTop = chatBody.scrollHeight;
  }

  // Xử lý câu trả lời của chatbot
  function getBotReply(question) {
    const q = question.toLowerCase();

    if (
      q.includes('xin chào') ||
      q.includes('hello') ||
      q.includes('hi')
    ) {
      return 'Xin chào! Bạn cần mình hỗ trợ chức năng nào của PharmaPro?';
    }

    if (
      q.includes('sản phẩm') ||
      q.includes('thuốc') ||
      q.includes('thuốc nào')
    ) {
      return 'Bạn có thể vào mục Sản phẩm để xem danh sách thuốc, tìm kiếm, thêm mới hoặc chỉnh sửa thông tin sản phẩm. Đây là hướng dẫn mẫu trong phiên bản MOCK.';
    }

    if (
      q.includes('tồn kho') ||
      q.includes('hết hàng') ||
      q.includes('sắp hết')
    ) {
      return 'Bạn hãy mở mục Tồn kho để kiểm tra số lượng hàng hóa. Nên chú ý các sản phẩm sắp hết hàng hoặc đã hết hàng để chủ động nhập thêm. Dữ liệu hiện tại là dữ liệu MOCK.';
    }

    if (
      q.includes('đơn hàng') ||
      q.includes('hóa đơn') ||
      q.includes('doanh thu')
    ) {
      return 'Bạn có thể vào mục Đơn hàng để xem lịch sử giao dịch và trạng thái đơn hàng. Để tạo đơn bán mới, hãy mở màn hình Bán hàng.';
    }

    if (
      q.includes('khách hàng') ||
      q.includes('khach hang') ||
      q.includes('tích điểm')
    ) {
      return 'Vào mục Khách hàng để tìm kiếm, thêm mới, chỉnh sửa thông tin khách hàng và xem điểm tích lũy.';
    }

    if (
      q.includes('bán hàng') ||
      q.includes('pos') ||
      q.includes('thanh toán')
    ) {
      return 'Bạn hãy mở màn hình Bán hàng (POS), chọn sản phẩm, nhập số lượng và thực hiện thanh toán theo hướng dẫn trên màn hình.';
    }

    if (
      q.includes('nhân viên') ||
      q.includes('tài khoản')
    ) {
      return 'Chức năng Nhân viên dùng để quản lý thông tin tài khoản và nhân sự. Bạn cần quyền phù hợp để sử dụng chức năng này.';
    }

    if (
      q.includes('cảm ơn') ||
      q.includes('cam on')
    ) {
      return 'Rất vui được hỗ trợ bạn! Chúc bạn sử dụng PharmaPro hiệu quả.';
    }

    return 'Mình chưa có câu trả lời phù hợp cho câu hỏi này. Bạn hãy thử hỏi về sản phẩm, tồn kho, khách hàng, đơn hàng hoặc bán hàng nhé.';
  }

  // Sự kiện mở/đóng chatbot
  toggleButton.addEventListener('click', () => {
    if (chatWindow.classList.contains('open')) {
      closeChat();
    } else {
      openChat();
    }
  });

  closeButton.addEventListener('click', closeChat);

  // Gửi tin nhắn
  chatForm.addEventListener('submit', function (event) {
    event.preventDefault();

    const question = chatInput.value.trim();

    if (!question) return;

    addMessage(question, 'user');

    chatInput.value = '';

    // Phản hồi mẫu
    window.setTimeout(() => {
      addMessage(getBotReply(question), 'bot');
    }, 350);
  });

  // Xử lý các câu hỏi gợi ý
  wrapper.querySelectorAll('[data-question]').forEach(button => {
    button.addEventListener('click', () => {
      const question = button.dataset.question;

      addMessage(question, 'user');

      window.setTimeout(() => {
        addMessage(getBotReply(question), 'bot');
      }, 350);
    });
  });

})();
