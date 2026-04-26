<?php
// SweetAlert System Feedback
class SweetAlert {
    // Show success message
    public static function success($title, $message = '', $redirect = '') {
        self::showAlert('success', $title, $message, $redirect);
    }
    
    // Show error message
    public static function error($title, $message = '', $redirect = '') {
        self::showAlert('error', $title, $message, $redirect);
    }
    
    // Show warning message
    public static function warning($title, $message = '', $redirect = '') {
        self::showAlert('warning', $title, $message, $redirect);
    }
    
    // Show info message
    public static function info($title, $message = '', $redirect = '') {
        self::showAlert('info', $title, $message, $redirect);
    }
    
    // Show confirmation dialog
    public static function confirm($title, $message, $confirmCallback, $cancelCallback = '') {
        $confirmCallback = htmlspecialchars($confirmCallback, ENT_QUOTES, 'UTF-8');
        $cancelCallback = htmlspecialchars($cancelCallback, ENT_QUOTES, 'UTF-8');
        
        $script = "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            <script>
                Swal.fire({
                    title: '$title',
                    text: '$message',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes',
                    cancelButtonText: 'No',
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $confirmCallback;
                    } else if ('$cancelCallback') {
                        $cancelCallback;
                    }
                });
            </script>
        ";
        
        return $script;
    }
    
    // Internal method to show alerts
    private static function showAlert($type, $title, $message, $redirect) {
        $typeMap = [
            'success' => 'success',
            'error' => 'error',
            'warning' => 'warning',
            'info' => 'info'
        ];
        
        $icon = $typeMap[$type] ?? 'info';
        $redirectScript = $redirect ? "window.location.href = '$redirect';" : '';
        
        $script = "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            <script>
                Swal.fire({
                    icon: '$icon',
                    title: '$title',
                    text: '$message',
                    timer: 3000,
                    showConfirmButton: true,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#3085d6'
                }).then(() => {
                    $redirectScript
                });
            </script>
        ";
        
        return $script;
    }
    
    // Show loading state
    public static function loading($title = 'Loading...', $message = '') {
        $script = "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            <script>
                Swal.fire({
                    title: '$title',
                    text: '$message',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });
            </script>
        ";
        
        return $script;
    }
    
    // Hide loading state
    public static function hideLoading() {
        return "
            <script>
                Swal.close();
            </script>
        ";
    }
}
?>