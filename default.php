<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome</title>
    <style>
        :root {
            
            --ios-bg: #F2F2F7;
            --ios-card-bg: #FFFFFF;
            --ios-text: #000000;
            --ios-blue: #007AFF;
            --ios-gray: #8E8E93;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--ios-bg);
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: var(--ios-text);
        }

        .container {
            width: 100%;
            max-width: 400px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        h1 {
            text-align: center;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--ios-text);
        }

        .card {
            background-color: var(--ios-card-bg);
            border-radius: 14px;
            padding: 20px;
            text-decoration: none;
            color: var(--ios-text);
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            transition: transform 0.1s ease, background-color 0.2s ease;
        }

        .card:active {
            transform: scale(0.98);
            background-color: #E5E5EA;
        }

        .card-content {
            display: flex;
            flex-direction: column;
        }

        .card-title {
            font-size: 17px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .card-subtitle {
            font-size: 13px;
            color: var(--ios-gray);
        }

        .card-icon {
            color: var(--ios-blue);
            font-size: 24px;
            /* Simple arrow chevron */
        }
        
        .chevron {
            width: 10px;
            height: 10px;
            border-top: 2px solid #C7C7CC;
            border-right: 2px solid #C7C7CC;
            transform: rotate(45deg);
        }

    </style>
</head>
<body>

    <div class="container">
        <h1>Welcome</h1>

        <a href="cms/visitors/" class="card">
            <div class="card-content">
                <span class="card-title">Visitor Entry</span>
                <span class="card-subtitle">Check-in and registration</span>
            </div>
            <div class="chevron"></div>
        </a>

        <a href="cms/volunteers/" class="card">
            <div class="card-content">
                <span class="card-title">Volunteer</span>
                <span class="card-subtitle">Team portal access</span>
            </div>
            <div class="chevron"></div>
        </a>

        <a href="cms/leaders/" class="card">
            <div class="card-content">
                <span class="card-title">Leaders</span>
                <span class="card-subtitle">Management dashboard</span>
            </div>
            <div class="chevron"></div>
        </a>

    </div>

</body>
</html>
