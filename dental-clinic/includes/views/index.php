<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../header.php';
?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow"><span class="eyebrow-dot"></span> A brighter kind of dental care</p>
        <h1>Good care.<br><em>Good feeling.</em></h1>
        <p class="hero-intro">Thoughtful dentistry, friendly people, and a calmer way to look after your smile.</p>
        <div class="hero-actions">
            <?php if (is_logged_in()): ?>
                <a class="button button-primary" href="<?= e(url(role_home(current_role()))) ?>">Go to your dashboard <span aria-hidden="true">&#8594;</span></a>
            <?php else: ?>
                <a class="button button-primary" href="<?= e(url('register.php')) ?>">Get started <span aria-hidden="true">&#8594;</span></a>
                <a class="text-link" href="<?= e(url('login.php')) ?>">I already have an account</a>
            <?php endif; ?>
        </div>
        <div class="hero-note"><span class="note-rule"></span><span>Care for every stage of your smile</span></div>
    </div>
    <div class="hero-art">
        <div class="art-caption"><span>01</span><span>Feel at ease</span></div>
        <img class="hero-photo" src="https://images.unsplash.com/photo-1606811971618-4486d14f3f99?auto=format&amp;fit=crop&amp;w=1200&amp;q=85" alt="A patient receiving dental care" fetchpriority="high">
        <div class="art-stamp"><span>SMILE</span><strong>with<br>confidence</strong></div>
    </div>
</section>

<section class="trust-strip" aria-label="Clinic commitments">
    <div><span class="trust-number">01</span><span>Care that listens</span></div>
    <div><span class="trust-number">02</span><span>Clear treatment plans</span></div>
    <div><span class="trust-number">03</span><span>A team you can trust</span></div>
</section>

<section class="services-section" id="services">
    <div class="section-heading">
        <div><p class="eyebrow">Care, made personal</p><h2>Find your next step.</h2></div>
        <p>From a routine clean to a little extra care, our team is here to help you feel good about your smile.</p>
    </div>
    <?php if ($services): ?>
        <div class="service-grid">
            <?php foreach ($services as $index => $service): ?>
                <article class="service-item">
                    <span class="service-index">0<?= $index + 1 ?></span>
                    <div class="service-content">
                        <p class="service-category"><?= e($service['category'] ?: 'Dental care') ?></p>
                        <h3><?= e($service['name']) ?></h3>
                        <p><?= e($service['description'] ?: 'Personalized care from our dental team.') ?></p>
                        <div class="service-meta"><span><?= (int) $service['duration_minutes'] ?> min</span><span><?= e(format_money($service['price'])) ?></span></div>
                    </div>
                    <span class="service-arrow" aria-hidden="true">&#8599;</span>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="empty-state">Our services are being updated. Please check back soon.</p>
    <?php endif; ?>
</section>

<section class="closing-band">
    <div><p class="eyebrow">Your smile, your pace</p><h2>Ready when you are.</h2></div>
    <a class="button button-light" href="<?= e(url(is_logged_in() ? role_home(current_role()) : 'register.php')) ?>"><?= is_logged_in() ? 'Open your dashboard' : 'Create your account' ?> <span aria-hidden="true">&#8594;</span></a>
</section>
<?php require __DIR__ . '/../footer.php'; ?>