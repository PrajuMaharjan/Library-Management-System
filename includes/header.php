<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-top {
            background: rgba(0,0,0,0.2);
            padding: 10px 0;
        }

        .header-top .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9em;
        }

        .contact-info {
            display: flex;
            gap: 20px;
        }

        .contact-info span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .header-main {
            padding: 20px 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            font-size: 2em;
            font-weight: bold;
            text-decoration: none;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            color: #3498db;
        }

        .tagline {
            font-size: 0.5em;
            opacity: 0.9;
            font-weight: normal;
        }

        nav {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        nav a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        nav a:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }

        nav a.active {
            background: rgba(255,255,255,0.3);
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .search-box {
            display: flex;
            background: rgba(255,255,255,0.2);
            border-radius: 25px;
            padding: 8px 15px;
            align-items: center;
        }

        .search-box input {
            background: transparent;
            border: none;
            color: white;
            outline: none;
            padding: 0 10px;
            width: 200px;
        }

        .search-box input::placeholder {
            color: rgba(255,255,255,0.7);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .user-profile:hover {
            background: rgba(255,255,255,0.3);
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3498db;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            nav {
                display: none;
            }
            
            .search-box input {
                width: 150px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-top">
            <div class="container">
                <div class="contact-info">
                    <span>📧 library@example.com</span>
                    <span>📞 +1 (555) 123-4567</span>
                </div>
                <div>
                    <span>⏰ Mon-Fri: 8:00 AM - 8:00 PM</span>
                </div>
            </div>
        </div>
        
        <div class="header-main">
            <div class="container">
                <div class="logo-section">
                    <a href="index.html" class="logo">
                        <div class="logo-icon">📚</div>
                        <div>
                            LibraryHub
                            <div class="tagline">Your Gateway to Knowledge</div>
                        </div>
                    </a>
                </div>

                <nav>
                    <a href="index.html" class="active">Home</a>
                    <a href="catalog.html">Catalog</a>
                    <a href="my-books.html">My Books</a>
                    <a href="services.html">Services</a>
                    <a href="events.html">Events</a>
                    <a href="about.html">About</a>
                </nav>

                <div class="user-section">
                    <div class="search-box">
                        <span>🔍</span>
                        <input type="text" placeholder="Search books...">
                    </div>
                    <div class="user-profile">
                        <div class="user-avatar">JD</div>
                        <span>John Doe</span>
                    </div>
                </div>
            </div>
        </div>
    </header>
</body>
</html>