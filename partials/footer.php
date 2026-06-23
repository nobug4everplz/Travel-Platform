    </main>
    <footer class="site-footer">
        <span>Travel Platform</span>
        <span>PHP + MySQL travel operations workspace</span>
    </footer>
    <?php if ($currentUser): ?>
    <script src="/assets/chat-widget.js" defer></script>
    <?php endif; ?>
    <?php if (!empty($loadSortable)): ?>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1/Sortable.min.js" defer></script>
    <?php endif; ?>
</body>
</html>
