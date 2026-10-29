@props(['cost', 'currencyType'])

<script>
const translations = {
    buy_confirmation: @json(__('badge_purchase_confirmation', ['cost' => $cost, 'currency' => $currencyType])),
    purchase_success: @json(__('badge_purchase_success', ['currency' => ucfirst($currencyType)])),
    purchase_error_insufficient: @json(__('badge_purchase_error_insufficient', ['currency' => $currencyType])),
    purchase_error_general: @json(__('badge_purchase_error_general')),
    missing_fields: @json(__('Please fill in the badge name, description, and draw something on the canvas.')),
    invalid_content: @json(__('Badge name and description cannot contain URLs.')),
    invalid_file_type: @json(__('Only PNG and GIF files are allowed.'))
};
</script>

{{ $slot }}

<script src="{{ asset('js/gif/gif.js') }}"></script>

<script>
    function badgeDrawer({ cost, currencyType }) {
        return {
            cost: cost,
            currencyType: currencyType,
            color: '#000000',
            recentColors: [],
            eraseMode: false,
            copyMode: false,
            showGrid: true,
            isDrawing: false,
            cellSideCount: 40,
            colorHistory: {},
            badgeName: '',
            badgeDescription: '',
            lastBuyTime: null,
            canBuy: true,
            buttonText: `{{ __('Buy Badge') }} (${cost} ${currencyType.charAt(0).toUpperCase() + currencyType.slice(1)})`,
            drawingContext: null,
            previewContext: null,
            guide: null,
            canvas: null,
            previewCanvas: null,
            colors: [
                '#000000', '#FFFFFF', '#808080', '#C0C0C0', '#FF0000', '#800000',
                '#FFFF00', '#808000', '#00FF00', '#008000', '#00FFFF', '#008080',
                '#0000FF', '#000080', '#FF00FF', '#800080', '#FF4500', '#FFA500',
                '#FFD700', '#F0E68C', '#90EE90', '#98FB98', '#AFEEEE', '#ADD8E6',
                '#87CEFA', '#6495ED', '#DDA0DD', '#EE82EE', '#A52A2A', '#D2691E',
                '#CD853F', '#F4A460', '#FFC0CB', '#9370DB', '#228B22', '#20B2AA'
            ],

            get isValid() {
                return this.badgeName.trim().length > 0 && this.badgeName.trim().length <= 24 && this.badgeDescription.trim().length > 0 && this.badgeDescription.trim().length <= 255 && Object.keys(this.colorHistory).length > 0 && !this.badgeName.match(/https?:\/\/|www\./i) && !this.badgeDescription.match(/https?:\/\/|www\./i) && this.canBuy;
            },

            init() {
                this.canvas = this.$refs.canvas;
                this.previewCanvas = this.$refs.previewCanvas;
                this.guide = this.$refs.guide;
                this.drawingContext = this.canvas.getContext('2d');
                this.previewContext = this.previewCanvas.getContext('2d');

                // Get actual rendered size of the canvas
                const canvasWidth = this.canvas.clientWidth;
                const cellSize = canvasWidth / this.cellSideCount;

                // Setup the guide with dynamic grid lines using gradients (no borders, no child divs)
                this.guide.style.width = `${canvasWidth}px`;
                this.guide.style.height = `${canvasWidth}px`;

                // Clear any existing children (no longer needed)
                this.guide.innerHTML = '';

                // Determine border color based on dark mode
                const isDark = document.documentElement.classList.contains('dark');
                const borderColor = isDark ? '#fff' : '#4b5563'; // gray-700

                // Set grid lines as background gradients
                this.guide.style.backgroundImage = `
                    repeating-linear-gradient(to bottom, ${borderColor} 0px 1px, transparent 1px ${cellSize}px),
                    repeating-linear-gradient(to right, ${borderColor} 0px 1px, transparent 1px ${cellSize}px)
                `;

                // Watch for grid toggle (use block instead of grid)
                this.$watch('showGrid', (value) => {
                    this.guide.style.display = value ? 'block' : 'none';
                });

                // Make checkerboard dynamic and aligned to cellSize
                const checkerboardEl = this.$el.querySelector('.checkerboard');
                let bgColor, checkColor;
                if (isDark) {
                    bgColor = '#1a1a1a';
                    checkColor = '#2a2a2a';
                } else {
                    bgColor = '#fff';
                    checkColor = '#eee';
                }
                const checkUnit = cellSize / 2; // Half cell for finer checks (2x2 per cell)
                checkerboardEl.style.backgroundColor = bgColor;
                checkerboardEl.style.backgroundImage = `
                    linear-gradient(45deg, ${checkColor} 25%, transparent 25%),
                    linear-gradient(-45deg, ${checkColor} 25%, transparent 25%),
                    linear-gradient(45deg, transparent 75%, ${checkColor} 75%),
                    linear-gradient(-45deg, transparent 75%, ${checkColor} 75%)
                `;
                checkerboardEl.style.backgroundSize = `${checkUnit * 2}px ${checkUnit * 2}px`;
                checkerboardEl.style.backgroundPosition = `0 0, 0 ${checkUnit}px, ${checkUnit}px -${checkUnit}px, -${checkUnit}px 0px`;

                // Watch for erase/copy toggle
                this.$watch('eraseMode', (value) => {
                    if (value) this.copyMode = false;
                });
                this.$watch('copyMode', (value) => {
                    if (value) this.eraseMode = false;
                });

                // Mouse events
                this.canvas.addEventListener('mousedown', this.handleMousedown.bind(this));
                this.canvas.addEventListener('mousemove', this.handleMousemove.bind(this));
                this.canvas.addEventListener('mouseup', () => { this.isDrawing = false; });
                this.canvas.addEventListener('mouseleave', () => { this.isDrawing = false; });

                // Touch events
                this.canvas.addEventListener('touchstart', this.handleTouchstart.bind(this));
                this.canvas.addEventListener('touchmove', this.handleTouchmove.bind(this));
                this.canvas.addEventListener('touchend', () => { this.isDrawing = false; });
                this.canvas.addEventListener('touchcancel', () => { this.isDrawing = false; });

                // Initial preview
                this.updatePreview();
            },

            toggleCopyMode() {
                this.copyMode = !this.copyMode;
                if (this.copyMode) {
                    this.eraseMode = false;
                }
            },

            toggleEraseMode() {
                this.eraseMode = !this.eraseMode;
                if (this.eraseMode) {
                    this.copyMode = false;
                }
            },

            handleMousedown(e) {
                if (e.button !== 0) return;

                const canvasBoundingRect = this.canvas.getBoundingClientRect();
                const scaleX = this.canvas.width / canvasBoundingRect.width;
                const scaleY = this.canvas.height / canvasBoundingRect.height;
                const x = (e.clientX - canvasBoundingRect.left) * scaleX;
                const y = (e.clientY - canvasBoundingRect.top) * scaleY;
                const cellX = Math.floor(x);
                const cellY = Math.floor(y);
                const currentColor = this.colorHistory[`${cellX}_${cellY}`];

                if (this.copyMode) {
                    if (currentColor) {
                        this.color = currentColor;
                        this.copyMode = false;
                    }
                } else if (e.ctrlKey) {
                    if (currentColor) {
                        this.color = currentColor;
                    }
                } else {
                    this.paintCell(cellX, cellY);
                    this.isDrawing = true;
                }
            },

            handleMousemove(e) {
                if (!this.isDrawing) return;

                const canvasBoundingRect = this.canvas.getBoundingClientRect();
                const scaleX = this.canvas.width / canvasBoundingRect.width;
                const scaleY = this.canvas.height / canvasBoundingRect.height;
                const x = (e.clientX - canvasBoundingRect.left) * scaleX;
                const y = (e.clientY - canvasBoundingRect.top) * scaleY;
                const cellX = Math.floor(x);
                const cellY = Math.floor(y);

                this.paintCell(cellX, cellY);
            },

            handleTouchstart(e) {
                e.preventDefault();
                const touch = e.touches[0];
                const canvasBoundingRect = this.canvas.getBoundingClientRect();
                const scaleX = this.canvas.width / canvasBoundingRect.width;
                const scaleY = this.canvas.height / canvasBoundingRect.height;
                const x = (touch.clientX - canvasBoundingRect.left) * scaleX;
                const y = (touch.clientY - canvasBoundingRect.top) * scaleY;
                const cellX = Math.floor(x);
                const cellY = Math.floor(y);
                const currentColor = this.colorHistory[`${cellX}_${cellY}`];

                if (this.copyMode) {
                    if (currentColor) {
                        this.color = currentColor;
                        this.copyMode = false;
                    }
                } else {
                    this.paintCell(cellX, cellY);
                    this.isDrawing = true;
                }
            },

            handleTouchmove(e) {
                e.preventDefault();
                if (!this.isDrawing) return;
                const touch = e.touches[0];
                const canvasBoundingRect = this.canvas.getBoundingClientRect();
                const scaleX = this.canvas.width / canvasBoundingRect.width;
                const scaleY = this.canvas.height / canvasBoundingRect.height;
                const x = (touch.clientX - canvasBoundingRect.left) * scaleX;
                const y = (touch.clientY - canvasBoundingRect.top) * scaleY;
                const cellX = Math.floor(x);
                const cellY = Math.floor(y);

                this.paintCell(cellX, cellY);
            },

            importImage(e) {
                const file = e.target.files[0];
                if (!file) return;

                if (!['image/png', 'image/gif'].includes(file.type)) {
                    alert(translations.invalid_file_type);
                    return;
                }

                if (!confirm(@js(__('Import image and overwrite the canvas?')))) {
                    e.target.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = (event) => {
                    const img = new Image();
                    img.onerror = () => {
                        alert(@js(__('Invalid image format. Please upload a valid PNG or GIF file.')));
                        e.target.value = '';
                    };
                    img.onload = () => {
                        const tempCanvas = document.createElement('canvas');
                        tempCanvas.width = this.cellSideCount;
                        tempCanvas.height = this.cellSideCount;
                        const tempContext = tempCanvas.getContext('2d');
                        tempContext.drawImage(img, 0, 0, this.cellSideCount, this.cellSideCount);

                        // Binarize alpha to avoid semi-transparent pixels
                        const imageData = tempContext.getImageData(0, 0, this.cellSideCount, this.cellSideCount);
                        for (let i = 0; i < imageData.data.length; i += 4) {
                            const a = imageData.data[i + 3];
                            if (a < 128) {
                                imageData.data[i + 3] = 0;
                            } else {
                                imageData.data[i + 3] = 255;
                            }
                        }
                        tempContext.putImageData(imageData, 0, 0);

                        // Clear the board
                        this.drawingContext.clearRect(0, 0, this.canvas.width, this.canvas.height);
                        this.colorHistory = {};

                        // Draw the resized image
                        this.drawingContext.drawImage(tempCanvas, 0, 0);

                        // Update colorHistory
                        const importedImageData = this.drawingContext.getImageData(0, 0, this.cellSideCount, this.cellSideCount);
                        for (let y = 0; y < this.cellSideCount; y++) {
                            for (let x = 0; x < this.cellSideCount; x++) {
                                const index = (y * this.cellSideCount + x) * 4;
                                const r = importedImageData.data[index];
                                const g = importedImageData.data[index + 1];
                                const b = importedImageData.data[index + 2];
                                const a = importedImageData.data[index + 3];
                                if (a > 0) {
                                    const hexColor = this.rgbToHex(r, g, b);
                                    this.colorHistory[`${x}_${y}`] = hexColor;
                                    // Add to recentColors
                                    if (!this.recentColors.includes(hexColor)) {
                                        this.recentColors.unshift(hexColor);
                                        if (this.recentColors.length > 12) {
                                            this.recentColors = this.recentColors.slice(0, 12);
                                        }
                                    }
                                }
                            }
                        }

                        this.updatePreview();
                    };
                    img.src = event.target.result;
                };
                reader.readAsDataURL(file);
            },

            rgbToHex(r, g, b) {
                r = Math.round(r);
                g = Math.round(g);
                b = Math.round(b);
                return '#' + ((1 << 24) | (r << 16) | (g << 8) | b).toString(16).slice(1).toUpperCase();
            },

            paintCell(cellX, cellY) {
                if (this.eraseMode) {
                    this.drawingContext.clearRect(cellX, cellY, 1, 1);
                    delete this.colorHistory[`${cellX}_${cellY}`];
                } else {
                    this.drawingContext.fillStyle = this.color;
                    this.drawingContext.fillRect(cellX, cellY, 1, 1);
                    this.colorHistory[`${cellX}_${cellY}`] = this.color;
                    // Add to recentColors only when painting
                    if (!this.recentColors.includes(this.color)) {
                        this.recentColors.unshift(this.color);
                        if (this.recentColors.length > 12) {
                            this.recentColors = this.recentColors.slice(0, 12);
                        }
                    }
                }
                this.updatePreview();
            },

            clearBoard() {
                if (!confirm(@js(__('Clear the entire board?')))) return;
                this.drawingContext.clearRect(0, 0, this.canvas.width, this.canvas.height);
                this.colorHistory = {};
                this.recentColors = []; // Clear recent colors as well
                this.updatePreview();
            },

            updatePreview() {
                this.previewContext.clearRect(0, 0, this.previewCanvas.width, this.previewCanvas.height);
                this.previewContext.drawImage(this.canvas, 0, 0);
            },

            generateGifBlob() {
                return new Promise((resolve) => {
                    // Get image data to find used colors
                    const imageData = this.drawingContext.getImageData(0, 0, this.canvas.width, this.canvas.height);
                    let usedColors = new Set();
                    for (let y = 0; y < this.canvas.height; y++) {
                        for (let x = 0; x < this.canvas.width; x++) {
                            const idx = (y * this.canvas.width + x) * 4;
                            if (imageData.data[idx + 3] > 0) { // Only consider opaque pixels
                                const r = imageData.data[idx];
                                const g = imageData.data[idx + 1];
                                const b = imageData.data[idx + 2];
                                usedColors.add((r << 16) | (g << 8) | b); // Store as integer for efficiency
                            }
                        }
                    }

                    // Find an unused color for transparency placeholder, starting from 0xFF00FF
                    let transColor = 0xFF00FF;
                    while (usedColors.has(transColor)) {
                        transColor = (transColor + 1) % 0x1000000; // Increment and wrap around (unlikely to loop much)
                    }
                    const transHex = '#' + transColor.toString(16).padStart(6, '0').toUpperCase();

                    const tempCanvas = document.createElement('canvas');
                    tempCanvas.width = this.canvas.width;
                    tempCanvas.height = this.canvas.height;
                    const tempContext = tempCanvas.getContext('2d');
                    tempContext.fillStyle = transHex; // Use the dynamic placeholder
                    tempContext.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
                    tempContext.drawImage(this.canvas, 0, 0);

                    const gif = new GIF({
                        workers: 2,
                        quality: 10,
                        workerScript: '{{ asset('js/gif/gif.worker.js') }}', // Local worker script
                        width: this.canvas.width,
                        height: this.canvas.height,
                        transparent: transColor // Use the dynamic integer value
                    });

                    gif.addFrame(tempCanvas);

                    gif.on('finished', (blob) => {
                        resolve(blob);
                    });

                    gif.render();
                });
            },

            async generateCanvas(action) {
                const blob = await this.generateGifBlob();

                if (action === 'download') {
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'badge.gif';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                }
            },

            async buyBadge() {
                if (Object.keys(this.colorHistory).length === 0 || !this.badgeName.trim() || !this.badgeDescription.trim()) {
                    alert(translations.missing_fields);
                    return;
                }

                if (this.badgeName.match(/https?:\/\/|www\./i) || this.badgeDescription.match(/https?:\/\/|www\./i)) {
                    alert(translations.invalid_content);
                    return;
                }

                if (!this.canBuy || !confirm(translations.buy_confirmation)) return;

                this.lastBuyTime = Date.now();
                this.canBuy = false;
                this.buttonText = `Cooldown (${Math.ceil((30000 - (Date.now() - this.lastBuyTime)) / 1000)} sec)`;

                const interval = setInterval(() => {
                    const remainingTime = Math.ceil((30000 - (Date.now() - this.lastBuyTime)) / 1000);
                    if (remainingTime <= 0) {
                        clearInterval(interval);
                        this.canBuy = true;
                        this.buttonText = `{{ __('Buy Badge') }} (${this.cost} ${this.currencyType.charAt(0).toUpperCase() + this.currencyType.slice(1)})`;
                    } else {
                        this.buttonText = `Cooldown (${remainingTime} sec)`;
                    }
                }, 1000);

                const blob = await this.generateGifBlob();
                const reader = new FileReader();
                reader.onloadend = () => {
                    const base64data = reader.result;

                    fetch('{{ route('badge.buy') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ badge_data: base64data, badge_name: this.badgeName, badge_description: this.badgeDescription })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(translations.purchase_success);
                        } else {
                            alert(data.message || translations.purchase_error_insufficient);
                            clearInterval(interval); // Clear interval if buy fails
                            this.canBuy = true;
                            this.buttonText = `{{ __('Buy Badge') }} (${this.cost} ${this.currencyType.charAt(0).toUpperCase() + this.currencyType.slice(1)})`;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert(translations.purchase_error_general);
                        clearInterval(interval); // Clear interval if buy fails
                        this.canBuy = true;
                        this.buttonText = `{{ __('Buy Badge') }} (${this.cost} ${this.currencyType.charAt(0).toUpperCase() + this.currencyType.slice(1)})`;
                    });
                };
                reader.readAsDataURL(blob);

                // Ensure cooldown ends even if fetch fails or takes long
                setTimeout(() => {
                    if (!this.canBuy) {
                        clearInterval(interval);
                        this.canBuy = true;
                        this.buttonText = `{{ __('Buy Badge') }} (${this.cost} ${this.currencyType.charAt(0).toUpperCase() + this.currencyType.slice(1)})`;
                    }
                }, 30000);
            }
        }
    }
</script>
