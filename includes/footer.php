
<?php
/*
|--------------------------------------------------------------------------
| Common Footer
|--------------------------------------------------------------------------
*/

// Show the floating AI Assistant button only to logged-in users.
$showAiAssistantButton = false;

if (function_exists('currentUser')) {
    $footerUser = currentUser();
    $showAiAssistantButton = !empty($footerUser);
}
?>

        </div>
        <!-- End page-container -->

    </main>
    <!-- End main-content -->

</div>
<!-- End app-layout -->


<?php if ($showAiAssistantButton): ?>
    <!-- Floating AI Assistant Button -->
    <a
        href="ai-assistant.php"
        class="floating-ai-assistant"
        aria-label="Open AI Assistant"
        title="AI Assistant"
    >
        <span class="floating-ai-tooltip">AI Assistant</span>

        <svg
            class="floating-ai-icon"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M12 2.5L14.4 9.6L21.5 12L14.4 14.4L12 21.5L9.6 14.4L2.5 12L9.6 9.6L12 2.5Z"
                fill="currentColor"
            />
            <path
                d="M19 2L19.8 4.2L22 5L19.8 5.8L19 8L18.2 5.8L16 5L18.2 4.2L19 2Z"
                fill="currentColor"
            />
        </svg>
    </a>
<?php endif; ?>


<style>
    .floating-ai-assistant {
        position: fixed;
        right: 24px;
        bottom: 24px;
        z-index: 9999;
        width: 68px;
        height: 68px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        color: #ffffff;
        text-decoration: none;
        background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
        box-shadow: 0 10px 28px rgba(37, 99, 235, 0.32);
        transition:
            transform 0.2s ease,
            box-shadow 0.2s ease;
    }

    .floating-ai-assistant:hover {
        color: #ffffff;
        transform: translateY(-4px) scale(1.04);
        box-shadow: 0 14px 32px rgba(37, 99, 235, 0.42);
    }

    .floating-ai-assistant:focus-visible {
        outline: 3px solid #93c5fd;
        outline-offset: 4px;
    }

    .floating-ai-icon {
        width: 32px;
        height: 32px;
    }

    .floating-ai-tooltip {
        position: absolute;
        right: 0;
        bottom: calc(100% + 12px);
        padding: 9px 14px;
        border-radius: 9px;
        color: #ffffff;
        background: #10294f;
        font-size: 14px;
        font-weight: 600;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transform: translateY(5px);
        transition:
            opacity 0.2s ease,
            transform 0.2s ease,
            visibility 0.2s ease;
        pointer-events: none;
    }

    .floating-ai-tooltip::after {
        content: "";
        position: absolute;
        top: 100%;
        right: 24px;
        border: 6px solid transparent;
        border-top-color: #10294f;
    }

    .floating-ai-assistant:hover .floating-ai-tooltip,
    .floating-ai-assistant:focus-visible .floating-ai-tooltip {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    @media (max-width: 600px) {
        .floating-ai-assistant {
            right: 16px;
            bottom: 16px;
            width: 58px;
            height: 58px;
        }

        .floating-ai-icon {
            width: 28px;
            height: 28px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .floating-ai-assistant,
        .floating-ai-tooltip {
            transition: none;
        }
    }
</style>


<!-- =========================================================
     JAVASCRIPT
     ========================================================= -->

<script src="../assets/js/script.js?v=2"></script>

</body>

</html>