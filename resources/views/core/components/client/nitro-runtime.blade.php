<script>
        function toggleFullscreen() {
            if (document.fullscreenElement) {
                document.exitFullscreen();

                return;
            }

            document.documentElement.requestFullscreen();
        }

        function reloadClient() {
            window.location.href = window.location;
        }

        window.addEventListener('DOMContentLoaded', () => {
            function getOnlineUserCount() {
                fetch('{{ route('api.online-count') }}')
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(response) {
                        document.getElementById('online-count').innerHTML = response.data.onlineCount;
                    });
            }

            getOnlineUserCount();

            setInterval(function() {
                getOnlineUserCount();
            }, 15000);
        });
    </script>

    <script src="{{ asset('assets/js/atom.js') }}"></script>
