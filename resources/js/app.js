/**
 * Tự làm mới một danh sách (đang tạo audio / đang chuyển video thành text)
 * trong lúc còn mục chưa xong: <div data-live-list="/url">…[data-pending="1"]…</div>
 */
function startLiveList(el) {
    const url = el.dataset.liveList;
    let timer = null;

    const schedule = () => {
        clearTimeout(timer);
        if (el.querySelector('[data-pending="1"]')) {
            timer = setTimeout(tick, 3000);
        }
    };

    const tick = async () => {
        // Không thay DOM khi đang nghe audio để tránh bị ngắt tiếng.
        const playing = [...el.querySelectorAll('audio')].some((a) => !a.paused);
        if (!playing) {
            try {
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (res.ok) {
                    el.innerHTML = await res.text();
                }
            } catch (_) {
                /* mạng lỗi thoáng qua: thử lại ở lượt sau */
            }
        }
        schedule();
    };

    schedule();
}

document.querySelectorAll('[data-live-list]').forEach(startLiveList);

// ---- Popup (<dialog>) -------------------------------------------------------

function openModal(id) {
    const dlg = document.getElementById(id);
    if (dlg && !dlg.open) {
        dlg.showModal();
        // Đưa con trỏ vào ô nhập đầu tiên thay vì nút đóng
        dlg.querySelector('select, textarea, input:not([type=hidden]):not([type=range]):not([type=checkbox])')?.focus();
    }
    return dlg;
}

document.addEventListener('click', async (e) => {
    const opener = e.target.closest('[data-open]');
    if (opener) {
        openModal(opener.dataset.open);
        return;
    }

    if (e.target.closest('[data-close]')) {
        e.target.closest('dialog')?.close();
        return;
    }

    // Bấm ra vùng nền tối thì đóng popup
    if (e.target instanceof HTMLDialogElement && e.target.open) {
        const r = e.target.getBoundingClientRect();
        const outside = e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom;
        if (outside) {
            e.target.close();
        }
        return;
    }

    // Xem toàn bộ nội dung text của 1 audio (tải theo yêu cầu để danh sách nhẹ)
    const viewer = e.target.closest('[data-view-text]');
    if (viewer) {
        const dlg = openModal('text-view');
        dlg.querySelector('[data-slot="title"]').textContent = viewer.dataset.title || '';
        const body = dlg.querySelector('[data-slot="body"]');
        const again = dlg.querySelector('[data-slot="again"]');
        body.textContent = 'Đang tải…';
        again.hidden = true;
        try {
            const res = await fetch(viewer.dataset.viewText, { headers: { Accept: 'application/json' } });
            const json = await res.json();
            body.textContent = json.text;
            again.href = json.again_url;
            again.hidden = false;
        } catch (_) {
            body.textContent = 'Không tải được nội dung.';
        }
    }
});

// Tự mở popup khi server yêu cầu (form lỗi, thao tác xong muốn ở lại popup, prefill…)
const autoOpen = document.body.dataset.openModal;
if (autoOpen) {
    openModal(autoOpen);
}

// Xác nhận trước khi xoá (ủy quyền sự kiện nên cũng chạy với phần tải lại bằng JS)
document.addEventListener('submit', (e) => {
    const msg = e.target.dataset?.confirm;
    if (msg && !confirm(msg)) {
        e.preventDefault();
    }
});

// Nút gửi form có thao tác lâu (tải YouTube...): khoá nút và báo đang xử lý để không bấm lặp
document.addEventListener('submit', (e) => {
    if (e.defaultPrevented) return;
    const btn = e.target.querySelector?.('[data-busy]');
    if (btn) {
        // disable sau khi form đã được gửi đi, nếu không trình duyệt sẽ bỏ qua nút
        setTimeout(() => {
            btn.disabled = true;
            btn.textContent = btn.dataset.busy;
        }, 0);
    }
});
