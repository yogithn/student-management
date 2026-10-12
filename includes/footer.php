            </main> <!-- Closing content-wrapper -->
        </div> <!-- Closing main-panel -->
    </div> <!-- Closing dashboard-container -->

    <!-- Global Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteConfirmModal">
        <div class="modal" style="max-width: 420px;">
            <div class="modal-header" style="border-bottom: none; padding-bottom: 0.5rem;">
                <h3 style="color: var(--danger); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-exclamation-triangle"></i> Confirm Deletion
                </h3>
                <button class="modal-close" onclick="closeModal('deleteConfirmModal')">&times;</button>
            </div>
            <div class="modal-body" style="padding-top: 0.5rem;">
                <p id="deleteConfirmMessage">Are you sure you want to delete this item? This action cannot be undone.</p>
            </div>
            <div class="modal-footer" style="border-top: none; background: transparent; padding-top: 0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteConfirmModal')" style="width: auto; padding: 0.5rem 1.25rem;">Cancel</button>
                <a id="deleteConfirmBtn" href="#" class="btn btn-danger" style="width: auto; padding: 0.5rem 1.25rem; display: inline-flex;">Confirm Delete</a>
            </div>
        </div>
    </div>

    <!-- JS Scripts -->
    <script src="../assets/js/main.js"></script>
</body>
</html>
