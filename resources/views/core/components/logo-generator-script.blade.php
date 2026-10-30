<script
        src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"
        integrity="sha384-ZZ1pncU3bQe8y31yfZdMFdSpttDoPmOZg2wguVK9almUodir1PghgT0eY7Mrty8H"
        crossorigin="anonymous"
        referrerpolicy="no-referrer"
    ></script>
<script>
        function logoGenerator() {
            return {
                fontType: 'atom',
                text: '',
                get generateLogoHtml() {
                    let sanitizedText = this.text.toLowerCase().replace(/[^a-z ]/g, '');
                    let html = '';
                    for (let i = 0; i < sanitizedText.length; i++) {
                        let letter = sanitizedText[i];
                        html += (letter === ' ') ? '<div style="width: 15px;"></div>' : `<img src="/assets/images/logo-generator/${this.fontType}/${letter}.png" alt="${letter}">`;
                    }
                    return html;
                },

                async generateCanvas(action) {
                    const logoContainer = document.querySelector('#logoContainer');
                    const originalStyles = logoContainer.getAttribute('style');

                    let combinedWidth = 0;
                    let maxHeight = 0;

                    logoContainer.childNodes.forEach(child => {
                        const rect = child.getBoundingClientRect();
                        combinedWidth += rect.width;
                        if (rect.height > maxHeight) {
                            maxHeight = rect.height;
                        }
                    });

                    combinedWidth += (logoContainer.childNodes.length - 1) * 2;
                    logoContainer.style.display = 'flex';

                    setTimeout(async () => {
                        const canvas = await html2canvas(logoContainer, {
                            backgroundColor: null,
                            width: combinedWidth,
                            height: maxHeight
                        });

                        logoContainer.setAttribute('style', originalStyles);

                        if (action === 'use') {
                            this.uploadLogo(canvas);
                        } else {
                            this.downloadCanvasAsImage(canvas);
                        }
                    }, 100);
                },

                async uploadLogo(canvas) {
                    canvas.toBlob(async (blob) => {
                        const formData = new FormData();
                        const sanitizedFileName = `${this.text.replace(/[^a-zA-Z0-9]/g, '_')}_${Date.now()}.png`;
                        formData.append('logo', blob, sanitizedFileName);

                        await fetch('{{ route('store.generated-logo') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': "{{ csrf_token() }}"
                            },
                            body: formData
                        }).then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    window.toast(data.message);

                                    setTimeout(() => {
                                        window.location.href = "{{ url()->current() }}";
                                    }, 1000);
                                } else {
                                    window.toast('{{ __('Something went wrong') }}', 'error');
                                }
                            });
                    }, 'image/png');
                },

                async downloadCanvasAsImage(canvas) {
                    const a = document.createElement('a');
                    const sanitizedFileName = this.text.replace(/[^a-zA-Z0-9]/g, '_');
                    a.href = canvas.toDataURL("image/png", 1.0);
                    a.download = `${sanitizedFileName}.png`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                },

                selectFont(font) {
                    this.fontType = font;
                }
            }
        }
    </script>
