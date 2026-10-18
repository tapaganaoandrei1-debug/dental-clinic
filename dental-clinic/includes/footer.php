    </div>
</main>
<?php if ($operations_shell): ?>
    <footer class="operations-footer">SmileCare Dental · Clinic operations</footer>
    </div>
    </div>
<?php else: ?>
    <footer class="site-footer">
        <div class="container footer-inner">
            <a class="footer-brand" href="<?= e(url('index.php')) ?>">SmileCare Dental</a>
            <p>Care that keeps you smiling.</p>
            <span>&copy; <?= date('Y') ?> SmileCare Dental</span>
        </div>
    </footer>
<?php endif; ?>
</body>
</html>