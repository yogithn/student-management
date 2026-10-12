document.addEventListener('DOMContentLoaded', function() {
    // 1. Mobile Sidebar Toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('active');
        });
        
        // Close sidebar on clicking outside (on mobile)
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                    sidebar.classList.remove('active');
                }
            }
        });
    }

    // 2. Profile Photo Preview on File Select
    const photoInput = document.querySelector('.photo-upload-input');
    const photoPreview = document.querySelector('.photo-preview-target');
    
    if (photoInput && photoPreview) {
        photoInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                // Validate size (max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    showToast('File is too large. Maximum size is 2MB.', 'danger');
                    this.value = '';
                    return;
                }
                
                // Validate type
                const fileType = file.type;
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
                if (!allowedTypes.includes(fileType)) {
                    showToast('Only JPEG, PNG, JPG, and GIF images are allowed.', 'danger');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    photoPreview.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    }

    // 3. Custom Toast Notifications
    window.showToast = function(message, type = 'success', duration = 4000) {
        // Create toast container if it doesn't exist
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.style.position = 'fixed';
            container.style.top = '20px';
            container.style.right = '20px';
            container.style.zIndex = '9999';
            container.style.display = 'flex';
            container.style.flexDirection = 'column';
            container.style.gap = '10px';
            container.style.maxWidth = '350px';
            container.style.width = '100%';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `alert alert-${type}`;
        toast.style.margin = '0';
        toast.style.boxShadow = '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1)';
        toast.style.animation = 'slideInRight 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        toast.style.display = 'flex';
        toast.style.alignItems = 'center';
        toast.style.justifyContent = 'space-between';
        
        let icon = 'fa-check-circle';
        if (type === 'danger') icon = 'fa-exclamation-circle';
        if (type === 'warning') icon = 'fa-exclamation-triangle';
        if (type === 'info') icon = 'fa-info-circle';

        toast.innerHTML = `
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <i class="fas ${icon}"></i>
                <span>${message}</span>
            </div>
            <i class="fas fa-times" style="cursor: pointer; opacity: 0.7;" onclick="this.parentElement.remove()"></i>
        `;

        container.appendChild(toast);

        // Slide out and remove
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
            toast.addEventListener('animationend', () => {
                toast.remove();
            });
        }, duration);
    };

    // Add keyframe animations dynamically to header
    const styleSheet = document.createElement('style');
    styleSheet.innerHTML = `
        @keyframes slideInRight {
            from { transform: translateX(120%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(120%); opacity: 0; }
        }
    `;
    document.head.appendChild(styleSheet);

    // 4. Modal Helper functions
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    };

    // Close modal on clicking backdrop
    const modals = document.querySelectorAll('.modal-overlay');
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });

    // 5. Custom Delete Confirmation Dialog Setup
    window.confirmDelete = function(deleteUrl, itemName = 'this record') {
        const confirmModal = document.getElementById('deleteConfirmModal');
        const deleteConfirmLink = document.getElementById('deleteConfirmBtn');
        const deleteMessage = document.getElementById('deleteConfirmMessage');

        if (confirmModal && deleteConfirmLink) {
            if (deleteMessage) {
                deleteMessage.innerHTML = `Are you sure you want to delete <strong>${itemName}</strong>? This action cannot be undone.`;
            }
            deleteConfirmLink.href = deleteUrl;
            openModal('deleteConfirmModal');
        } else {
            // Fallback to default browser confirm if modal is missing
            if (confirm(`Are you sure you want to delete ${itemName}?`)) {
                window.location.href = deleteUrl;
            }
        }
    };
});
