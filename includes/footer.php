            </main>
            
            <?php if (is_logged_in()): ?>
            <!-- Footer -->
            <footer class="footer">
                <p>&copy; <?php echo date('Y'); ?> Ziyafat us Shukr. All rights reserved.</p>
            </footer>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?php echo isset($js_path) ? $js_path : '../assets/js/'; ?>script.js"></script>
</body>
</html>