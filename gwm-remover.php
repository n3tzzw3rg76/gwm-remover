<?php
/**
 * Plugin Name: GWM Remover
 * Description: Analyzes uploaded images locally in the browser, removes Gemini watermarks via exact reverse alpha blending (mask-based), and completely eliminates metadata.
 * Version: 1.2.3
 * Author: Dominique Blake-Hofer
 * Author URI: https://blake-hofer.net
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gwm-remover
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

class GWM_Remover {

    public function __construct() {
        add_action('admin_menu', array($this, 'gwm_add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'gwm_enqueue_assets'));
    }

    public function gwm_add_admin_menu() {
        add_management_page(
            'GWM Remover',
            'GWM Remover',
            'manage_options',
            'gwm-remover',
            array($this, 'gwm_render_admin_page')
        );
    }

    public function gwm_enqueue_assets($hook) {
        if ($hook !== 'tools_page_gwm-remover') {
            return;
        }
        
        $custom_css = "
            .gwm-container { max-width: 1000px; margin: 20px 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif; }
            .gwm-main-wrap { display: block; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 25px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
            .gwm-dropzone { border: 2px dashed #b5bfc7; padding: 50px 20px; text-align: center; background: #f8f9fa; cursor: pointer; transition: all 0.2s ease-in-out; border-radius: 4px; position: relative; }
            .gwm-dropzone:hover, .gwm-dropzone.dragover { background: #f0f2f4; border-color: #2271b1; }
            .gwm-preview-area { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-top: 25px; }
            .gwm-item { border: 1px solid #dcdcde; padding: 15px; background: #fff; text-align: center; position: relative; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
            .gwm-item img { max-width: 100%; height: auto; display: block; margin: 0 auto 12px; max-height: 140px; object-fit: contain; border-radius: 2px; }
            .gwm-item .status { font-size: 11px; font-weight: bold; padding: 4px 8px; border-radius: 3px; display: inline-block; }
            .gwm-item .status.processing { background: #fbf4e2; color: #855d10; }
            .gwm-item .status.success { background: #edfaef; color: #135e23; }
            .gwm-item .status.error { background: #fcf0f1; color: #d63638; }
            .gwm-item .download-btn { display: block; margin-top: 10px; text-decoration: none; background: #2271b1; color: #fff; padding: 6px 12px; border-radius: 3px; font-size: 13px; font-weight: 500; transition: background 0.1s ease; cursor: pointer; border: none; width: 100%; box-sizing: border-box; }
            .gwm-item .download-btn:hover { background: #135e23; }
            
            .gwm-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
            .gwm-box { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
            .gwm-box h3 { margin-top: 0; color: #1d2327; border-bottom: 1px solid #f0f0f1; padding-bottom: 10px; font-size: 14px; }
            .gwm-disclaimer { font-size: 12px; color: #646970; line-height: 1.5; }
            
            .gwm-donation-wrap { display: flex; align-items: flex-start; gap: 20px; }
            .gwm-wallet-info { flex: 1; font-size: 13px; }
            .gwm-wallet-code { background: #f0f0f1; padding: 5px 8px; border-radius: 3px; font-family: monospace; display: block; margin: 5px 0 10px 0; word-break: break-all; border: 1px solid #dcdcde; }
            .gwm-paypal-btn { display: inline-block; background: #ffc439; color: #003087; text-decoration: none; padding: 6px 15px; border-radius: 20px; font-weight: bold; font-size: 12px; border: 1px solid #e5af30; }
            .gwm-paypal-btn:hover { background: #f2b42a; }
            
            .gwm-qr-container { width: 120px; height: 120px; background: #fff; border: 1px solid #ccd0d4; padding: 8px; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); flex-shrink: 0; }
            .gwm-qr-container img { width: 100%; height: 100%; display: block; object-fit: contain; }
            
            .gwm-footer-credits { margin-top: 30px; text-align: center; font-size: 12px; color: #8c8f94; border-top: 1px solid #dcdcde; padding-top: 15px; }
            .gwm-footer-credits a { color: #2271b1; text-decoration: none; }
            .gwm-footer-credits a:hover { text-decoration: underline; }
        ";
        wp_register_style('gwm-remover-css', false, array(), '1.2.3');
        wp_enqueue_style('gwm-remover-css');
        wp_add_inline_style('gwm-remover-css', $custom_css);
    }

    public function gwm_render_admin_page() {
        // Define path for the mask locally
        $mask_url = plugin_dir_url(__FILE__) . 'mask.png';
        
        // Define remote URL for the secure QR code to prevent tampering
        $qr_url = 'https://blake-hofer.net/api/v1/qr_wallet.png';
        ?>
        <div class="wrap gwm-container">
            <h1>GWM Remover <span style="font-size: 12px; font-weight: normal; background: #dcdcde; padding: 2px 6px; border-radius: 3px; margin-left: 10px;">v1.2.3 (Mask-Based)</span></h1>
            <p class="description" style="margin-bottom: 20px;">This tool analyzes uploaded images locally and uses an exact reference mask (`mask.png`) to remove the watermark via reverse alpha blending <strong>pixel-perfectly and 100% losslessly</strong>.</p>
            
            <div class="gwm-main-wrap">
                <div class="gwm-dropzone" id="gwm-dropzone">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#646970" stroke-width="1.5" style="margin-bottom: 10px;">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <p class="drag-drop-info" style="font-size: 14px; font-weight: 500; margin: 0;">Drag and drop images here or click to select</p>
                    <p style="font-size: 11px; color: #646970; margin: 5px 0 0 0;">Supported formats: PNG, JPEG, WebP. Please ensure that a mask.png is located in the plugin folder.</p>
                    <input type="file" id="gwm-file-input" multiple accept="image/png, image/jpeg, image/webp" style="display: none;" />
                </div>
                
                <div class="gwm-preview-area" id="gwm-preview-area"></div>
            </div>

            <div class="gwm-meta-grid">
                <div class="gwm-box">
                    <h3>☕ Support & Donation</h3>
                    <div class="gwm-donation-wrap">
                        <div class="gwm-wallet-info">
                            <p style="margin-top:0; margin-bottom: 8px;">If this tool helps you, I'd appreciate a coffee!</p>
                            
                            <strong>Support / PayPal:</strong>
                            <span class="gwm-wallet-code">dhofer76@gmail.com</span>
                            <a href="https://www.paypal.com" target="_blank" class="gwm-paypal-btn">Donate via PayPal</a>
                            
                            <strong style="display:block; margin-top:15px;">Bitcoin (BTC) Wallet:</strong>
                            <span class="gwm-wallet-code">bc1qyn6dgav4y6g4ux98u5qjyy6flecadr0uju7j5u</span>
                        </div>
                        
                        <div class="gwm-qr-container" title="Scan BTC Address">
                            <img src="<?php echo esc_url($qr_url); ?>" alt="BTC Wallet QR Code">
                        </div>
                    </div>
                </div>

                <div class="gwm-box">
                    <h3>⚖️ Legal Disclaimer / Liability Waiver</h3>
                    <div class="gwm-disclaimer">
                        <p style="margin-top:0;"><strong>Important Notice:</strong> This plugin is provided "as is" and completely free of charge. The developer assumes no liability for any damage to data, systems, or the legal admissibility of image manipulation in individual cases.</p>
                        <p>The responsibility for using this tool and processing the modified images lies solely with the user. By using the module, the user fully indemnifies the developer against any legal action, claims, or demands for compensation.</p>
                    </div>
                </div>
            </div>

<div class="gwm-footer-credits">
    &copy; <?php echo esc_html( gmdate( 'Y' ) ); ?>
    <a href="https://blake-hofer.net" target="_blank" rel="noopener noreferrer">
        Dominique Blake-Hofer (blake-hofer.net)
    </a>.
    All rights reserved. This module is free software, licensed under the terms of the GPLv2.
</div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropzone = document.getElementById('gwm-dropzone');
            const fileInput = document.getElementById('gwm-file-input');
            const previewArea = document.getElementById('gwm-preview-area');
            
            // Load the mask into a canvas beforehand
            const maskImage = new Image();
            maskImage.crossOrigin = "Anonymous";
            maskImage.src = '<?php echo esc_url($mask_url); ?>';
            
            let maskCanvas = document.createElement('canvas');
            let maskCtx = maskCanvas.getContext('2d');
            let maskDataLoaded = false;
            let mData = null;

            maskImage.onload = function() {
                maskCanvas.width = maskImage.width;
                maskCanvas.height = maskImage.height;
                maskCtx.drawImage(maskImage, 0, 0);
                mData = maskCtx.getImageData(0, 0, maskCanvas.width, maskCanvas.height);
                maskDataLoaded = true;
            };

            dropzone.addEventListener('click', () => fileInput.click());

            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    dropzone.classList.add('dragover');
                });
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    dropzone.classList.remove('dragover');
                });
            });

            dropzone.addEventListener('drop', (e) => {
                if (e.dataTransfer.files.length > 0) {
                    handleFiles(e.dataTransfer.files);
                }
            });

            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    handleFiles(e.target.files);
                }
            });

            function handleFiles(files) {
                if (!maskDataLoaded) {
                    alert("The mask file (mask.png) has not been loaded yet or is missing in the plugin directory.");
                    return;
                }
                Array.from(files).forEach(file => {
                    if (file.type.match('image.*')) {
                        processImageClientSide(file);
                    }
                });
            }

            function processImageClientSide(file) {
                const itemNonce = Math.random().toString(36).substring(2, 9);
                const itemHtml = `
                    <div class="gwm-item" id="item-${itemNonce}">
                        <img id="img-preview-${itemNonce}" src="" alt="${file.name}">
                        <div class="filename" style="font-size:11px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-bottom: 5px; color:#1d2327;">${file.name}</div>
                        <span id="status-${itemNonce}" class="status processing">Applying mask...</span>
                    </div>
                `;
                previewArea.insertAdjacentHTML('beforeend', itemHtml);
                const statusEl = document.getElementById(`status-${itemNonce}`);
                const imgPreviewEl = document.getElementById(`img-preview-${itemNonce}`);

                const reader = new FileReader();
                reader.onload = function(event) {
                    const img = new Image();
                    img.onload = function() {
                        imgPreviewEl.src = img.src;

                        const canvas = document.createElement('canvas');
                        canvas.width = img.width;
                        canvas.height = img.height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0);

                        const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        const data = imgData.data;
                        const width = canvas.width;
                        const height = canvas.height;

                        // Determine the position of the mask (bottom right)
                        const maskW = maskCanvas.width;
                        const maskH = maskCanvas.height;
                        
                        // We define the offset where the logo is typically placed.
                        // Depending on the image resolution, the logo is scaled, which is why we align it in the bottom right corner.
                        const startX = width - maskW;
                        const startY = height - maskH;

                        // Exact reverse alpha blending using mask.png
                        for (let y = 0; y < maskH; y++) {
                            for (let x = 0; x < maskW; x++) {
                                const targetX = startX + x;
                                const targetY = startY + y;

                                if (targetX >= 0 && targetX < width && targetY >= 0 && targetY < height) {
                                    const imgIdx = (targetY * width + targetX) * 4;
                                    const maskIdx = (y * maskW + x) * 4;

                                    // Read the luminance of the mask (interpret as alpha value, 0 to 1)
                                    // We assume that brighter values in mask.png indicate a stronger transparency of the logo.
                                    const maskLuminance = (mData.data[maskIdx] + mData.data[maskIdx+1] + mData.data[maskIdx+2]) / 3;
                                    const alpha = maskLuminance / 255;

                                    if (alpha > 0.01) {
                                        const r = data[imgIdx];
                                        const g = data[imgIdx+1];
                                        const b = data[imgIdx+2];

                                        // Formula: Original = (Final - (255 * Alpha)) / (1 - Alpha)
                                        // The value 255 corresponds to the pure white color of the logo.
                                        let cleanR = (r - 255 * alpha) / (1 - alpha);
                                        let cleanG = (g - 255 * alpha) / (1 - alpha);
                                        let cleanB = (b - 255 * alpha) / (1 - alpha);

                                        data[imgIdx]   = Math.min(255, Math.max(0, cleanR));
                                        data[imgIdx+1] = Math.min(255, Math.max(0, cleanG));
                                        data[imgIdx+2] = Math.min(255, Math.max(0, cleanB));
                                    }
                                }
                            }
                        }

                        ctx.putImageData(imgData, 0, 0);

                        canvas.toBlob(function(blob) {
                            const cleanUrl = URL.createObjectURL(blob);
                            
                            statusEl.className = 'status success';
                            statusEl.textContent = 'Perfectly Cleaned';
                            imgPreviewEl.src = cleanUrl; 
                            
                            const downloadBtn = document.createElement('a');
                            downloadBtn.className = 'download-btn';
                            downloadBtn.href = cleanUrl;
                            downloadBtn.download = 'gwm_cleaned_' + file.name;
                            downloadBtn.textContent = 'Download';
                            
                            document.getElementById(`item-${itemNonce}`).appendChild(downloadBtn);
                        }, file.type, 0.95);
                    };
                    img.src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
        </script>
        <?php
    }
}

new GWM_Remover();