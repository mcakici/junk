<?php
trait sendmail {
    protected $email_from = "patites@yandex.com";
    protected $email_from_name = "Patites";

    public function send_mail($toEmail,$toName,$subject = null,$message = null){
        if($message === null || empty($message) || $subject === null || empty($subject) || !$toEmail) return false;
        
        require_once ABSPATH.'vendor/phpmailer/phpmailer/src/Exception.php';
        require_once ABSPATH.'vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once ABSPATH.'vendor/phpmailer/phpmailer/src/SMTP.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $mail->IsSMTP();
        // $mail->SMTPDebug = 1; 
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = 'tls';
        $mail->Host = 'smtp.yandex.com';
        $mail->Port = 587;
        $mail->Username = 'patites@yandex.com';
        $mail->Password = 'Asd146641';
        $mail->CharSet = 'UTF-8';
        $mail->SetFrom($this->email_from, $this->email_from_name);
    
        $mail->AddAddress($toEmail, $toName);

        $mail->Subject = $subject;
        $mail->MsgHTML($message);
        if($result = $mail->Send()) {
            var_dump($result);
            return true;
        } else {
            var_dump($result);
            return false;
        }
    }
}
?>