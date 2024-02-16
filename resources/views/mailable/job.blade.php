<!DOCTYPE html>
<html lang="en">
<head>
    <style type="text/css" title="x-apple-mail-formatting"></style>
    <meta name="viewport" content="width = 375, initial-scale = -1"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="x-apple-disable-message-reformatting"/>
    <style type="text/css">
        .social {
            color: #0642ff;
            width: 17px;
            margin: 15px 6px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Poppins, Arial, Helvetica, sans-serif;
            background-color: #f5f5f5;
            padding: 40px;
        }

        .main {
            width: 100%;
            background-color: white;
            margin: 0 auto
        }

        .main-box {
            width: 640px;
            background-color: white;
            margin: 0 auto;
            padding: 40px 40px 20px 40px;
            border-radius: 20px;
            border: 1px solid lightgray;
        }

        .info-text {
            width: 504px;
            display: flex
        }

        .footer {
            font-size: 10px;
            line-height: 15px;
            width: 550px;
            text-align: center;
            margin: 0 auto;
            border-top: 1px solid #0642ff;
        }

        @media only screen and (max-width: 600px) {
            body {
                padding: 15px;
                width: 100%;
                margin: 0;
            }

            .main-box {
                width: 100%;
                background-color: white;
                margin: 0 auto;
                padding: 0;
                border-radius: 20px;
                border: 1px solid lightgray;
            }

            .main {
                margin: 20px auto;
            }

            .info-text {
                width: 100%;
                display: flex
            }

            .footer {
                width: 100%;
            }
        }
    </style>
</head>

<body>
<div class="main-box">
    <div class="main">
        <div class="header" style="height: 70px; padding: 0 25px">
            <a href="{{ env('APP_URL') }}">
                <img src="https://cdcinc.space/cdclogoblack.png" alt="CDCINC" title="CDCINC" style="display:block"
                     width="80" height="40"/>
            </a>
        </div>
        <div class="content" style="font-size: 12px; line-height: 15px; padding: 0 25px ;">
            <div class="contact" style="height: 20px; font-weight: bold; line-height: 15px">Dear {{ $notifiable->name }},
            </div>
            <div class="main-content">
                {{ $content }}
            </div>
            <div style="text-align: center">
                <a
                        href="{{ $url }}"
                        style="
                border: none;
                color: #ffffff;
                padding: 15px 20px;
                text-align: center;
                text-decoration: none;
                display: inline-block;
                font-size: 14px;
                font-weight: 400;
                margin: 32px 2px 20px 2px;

                cursor: pointer;
                background-color: rgb(16, 47, 84);
                border-radius: 10px;
                height: 46px;
              "
                >
                    {{ $title }}
                </a>
            </div>
            <div class="info-text">
                <img src="https://d415fpsfyedhm.cloudfront.net/assets/images/email-verification/danger-circle_1.png"
                     width="16" style="margin: 5px 6px 0" height="16">
                <div style="margin-top: 6px">

                    If you're having trouble clicking the "{{ $title }}" button, copy and paste the following URL into
                    your web browser: {{ $url }}
                </div>
            </div>
        </div>
    </div>
</body>
</html>
