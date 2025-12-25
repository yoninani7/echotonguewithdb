<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECHOTONGUE - Official Site</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="icon" href="assets/echologo.png" sizes="32x32" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/echologo.png">
    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Orbitron:wght@700;900&display=swap"
        rel="stylesheet">
   
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #c91313c9;
            --secondary-color: rgba(255, 255, 255, 0.884);
            --accent-color: #7d1c1c;
            --light-bg: #f9f0f0;
            --card-bg: #ffffff;
            --text-dark: #777776;
            --text-light: #6b4d3a;
            --text-muted: #a08c7a;
            --border-color: #e8d6d6;
            --shadow: 0 10px 30px rgba(139, 90, 43, 0.08);
            --shadow-hover: 0 15px 40px rgba(139, 90, 43, 0.15);
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
        }



        body {
            background-image: url(assets/leaves.png);
            scroll-behavior: smooth;
            animation: twinkle 10s infinite secondary;
    display: flex;
    flex-direction: column;
        }

        /* Optional: Gentle pulse to make stars "shimmer" */
        @keyframes twinkle {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.8;
            }
        }

        .authors-thoughts {
            position: relative;
            min-height: 100vh;
            margin-top: 20px;
        }

        /* Main container */
        .thoughts-container {
            max-width: 1100px;
            margin: 0 auto;
            position: relative;
        }

        /* Enhanced header */
        .thoughts-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 20px 20px;
            background: linear-gradient(160deg, #161616, #0a0a0a);
            color: #e0e0e0; 
            border-radius: 20px;
            box-shadow: rgb(110, 110, 110) 0 0 0px 0px;
            border: 1px solid rgba(14, 2, 2, 0.753);
            position: relative;
            z-index: 2;
        }

        .liner {
            content: ' ';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            width: 150px;
            height: 4px;
            background-color: var(--primary-red);
        }

        .author-name {
            
    font-family: 'Cinzel', serif;
            font-size: 1.9rem;
            font-weight: 650;
            color: rgb(240, 236, 236);
            margin-bottom: 18px;
            line-height: 0.9;

        }


        .author-bio {
            font-size: clamp(0.9rem, 2vw, 1.1rem);
            color: rgba(255, 255, 255, 0.884);
            max-width: 900px;
            line-height: 1.8;
            margin: 0 auto;
        }

        /* Timeline Container */
        .timeline-wrapper {
            position: relative;
            padding-left: 80px;
        }

        /* The background 'track' (The line that is always there) */
        .timeline-line {
            position: absolute;
            left: 30px;
            top: 0;
            bottom: 0;
            width: 3px;
            background: rgba(255, 255, 255, 0.1);
            /* Very faint white */
            z-index: 1;
        }

        /* The 'active' line that follows your scroll */
        .timeline-progress {
            position: absolute;
            left: 30px;
            top: 0;
            width: 3px;
            background: linear-gradient(to bottom,
                    #2e0202,
                    /* Dark Goldenrod */
                    #800404,
                    /* Deep Burgundy */
                    #990909, #990909, #990909, #990909,
                    /* Blood Red */
                    white);
            box-shadow: 0 0 15px #990909;
            z-index: 2;
            height: 0%;
            /* Starts at 0, JS will update this */
            transition: height 0.1s ease-out;
            /* Smooth growth */
        }

        /* Hover effect for timeline line when thought entry is hovered */
        .thought-entry:hover~.timeline-line {
            background: linear-gradient(to bottom,
                    transparent 0%,
                    var(--secondary-color) 10%,
                    var(--accent-color) 50%,
                    var(--secondary-color) 90%,
                    transparent 100%);
        }

        /* Thoughts Feed */
        .thoughts-feed {
            position: relative;
            z-index: 2;
        }

        /* Thought entries */
        .thought-entry {
            position: relative;
            margin-bottom: 50px;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.6s ease forwards;
            cursor: pointer;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Timeline Dot - Enhanced hover effect */
        /* Add this to center the timeline-dot within its parent container */
        .timeline-container {
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            top: 10px;
        }

        .timeline-dot {
            transform: rotate(45deg);
            width: 14px;
            height: 14px;
            background: rgb(248, 246, 246);
            border: 2px solid red;
            z-index: 3;
            transition: var(--transition);
        }

        .timeline-dot::after {
            content: '';
            position: absolute;
            inset: 3px;
            background: red;
            opacity: 0.2;
            border-radius: 100%;
            transition: var(--transition);
        }

        /* Timeline dot glow effect on hover */
        .thought-entry:hover .timeline-dot {
            transform: rotate(135deg) scale(1.3);
            border-color: rgb(167, 0, 0);
            box-shadow: 0 0 0 4px rgba(192, 187, 183, 0.1),
                0 0 0 8px rgba(139, 90, 43, 0.05);
        }

        .thought-entry:hover .timeline-dot::after {
            opacity: 0.8;
            background: var(--accent-color);
        }

        /* Create a larger invisible hit area for timeline interaction */
        .timeline-hit-area {
            position: absolute;
            left: 20px;
            top: 0;
            width: 40px;
            height: 100%;
            z-index: 4;
            cursor: pointer;
        }

        /* Container for the thought box */
        .thought-content {
            position: relative;
            /* Professional Deep Charcoal Gradient */
            background: linear-gradient(160deg, #1a1a1a 0%, #0a0a0a 100%);
            color: #e0e0e0;

            /* Muted Bronze/Gold Border */
            border: 1px solid #4a453e;
            border-radius: 12px;
            padding: 24px;

            /* Layered shadows for depth */
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);

            /* Clean typography */
            font-family: 'Inter', -apple-system, sans-serif;
            line-height: 1.6;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* The Smooth Arrow */
        .thought-content::before {
            content: '';
            position: absolute;
            left: -9px;
            top: 30px;
            width: 16px;
            height: 16px;

            /* Matches the top-section color of the gradient */
            background: #1a1a1a;

            border-left: 1px solid #4a453e;
            border-bottom: 1px solid #4a453e;

            /* Creates the smooth "point" */
            transform: rotate(45deg);
            border-bottom-left-radius: 4px;

            /* Ensures it sits behind text but above shadows */
            z-index: 1;
        }

        /* Hover Effect */
        .thought-content:hover {
            border-color: #ac0202;
            /* Highlights the border to a richer gold */
            transform: translateX(4px);
            /* Subtle shift to indicate interactivity */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
        }

        /* Date styling */
        .thought-date {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 25px;
            padding: 9px 15px;
            background: linear-gradient(135deg, rgba(153, 9, 9, 0.911), rgba(90, 2, 2, 0.747));
            border-radius: 20px;
            font-size: 0.85rem;
            color: rgb(236, 236, 236);
            font-weight: 600;
            transition: var(--transition);
        }

        /* Thought text */
        .thought-text {
            font-size: clamp(1rem, 2vw, 1.2rem);
            line-height: 1.7;
            color: rgba(255, 255, 255, 0.884);
            margin-bottom: 20px;
            padding-left: 20px;
            margin-top: 10px;
            position: relative;
        }

        .thought-text::before {
            content: '"';
            position: absolute;
            left: 0;
            top: -15px;
            font-size: 3rem;
            color: var(--secondary-color);
            opacity: 0.3;
            font-family: Georgia, serif;
            line-height: 1;
        }



        /* New indicator */
        .new-indicator {
            position: absolute;
            right: -10px;
            top: 10px;
            background: linear-gradient(135deg, rgb(153, 9, 9), rgba(90, 2, 2, 0.89));
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(139, 90, 43, 0.3);
            z-index: 4;
        }


        /* Responsive Design */
        @media (max-width: 992px) {
            .timeline-wrapper {
                padding-left: 70px;
            }

            .timeline-line {
                left: 35px;
            }

            .timeline-dot {
                left: 32px;
            }

            .timeline-hit-area {
                left: 15px;
                width: 35px;
            }
        }

        @media (max-width: 768px) {
            .authors-thoughts {
                padding: 80px 15px 40px;
            }

            .timeline-wrapper {
                padding-left: 50px;
            }

            .timeline-line {
                left: 25px;
            }

            .timeline-dot {
                left: 22px;
                width: 12px;
                height: 12px;
            }

            .timeline-hit-area {
                left: 10px;
                width: 30px;
            }

            .thought-content {
                margin-left: 15px;
                padding: 20px;
            }

            .thought-content::before {
                left: -9px;
                border-width: 9px 9px 9px 0;
            }

            .thought-content::after {
                left: -10px;
                border-width: 10px 10px 10px 0;
            }

            #main-nav {
                padding: 12px 20px;
                width: 95%;
            }

            .nav-brand {
                font-size: 1.2rem;
            }

            .btn-nav span {
                display: none;
            }

            .btn-nav {
                padding: 10px;
            }
        }

        @media (max-width: 480px) {
            .authors-thoughts {
                padding: 70px 10px 30px;
            }

            .thoughts-header {
                padding: 30px 15px;
                margin-bottom: 40px;
            }

            .timeline-wrapper {
                padding-left: 40px;
            }

            .timeline-line {
                left: 20px;
            }

            .timeline-dot {
                left: 17px;
                width: 10px;
                height: 10px;
            }

            .timeline-hit-area {
                left: 8px;
                width: 25px;
            }

            .thought-entry {
                margin-bottom: 35px;
            }

            .thought-content {
                padding: 18px;
                margin-left: 10px;
            }

            .thought-text {
                padding-left: 15px;
                font-size: 1rem;
            }

            .thought-text::before {
                font-size: 2.5rem;
                top: -10px;
            }

            .thought-content::before {
                left: -8px;
                top: 18px;
                border-width: 8px 8px 8px 0;
            }

            .thought-content::after {
                left: -9px;
                top: 17px;
                border-width: 9px 9px 9px 0;
            }

            #main-nav {
                padding: 10px 15px;
                border-radius: 30px;
            }

            .nav-brand {
                font-size: 1rem;
            }
        }

        @media (max-width: 360px) {
            .timeline-wrapper {
                padding-left: 35px;
            }

            .timeline-line {
                left: 17px;
            }

            .timeline-dot {
                left: 14px;
            }

            .timeline-hit-area {
                left: 6px;
                width: 20px;
            }

            .thought-content {
                padding: 15px;
            }

            .thought-date {
                font-size: 0.75rem;
                padding: 5px 12px;
            }


        }

        /* Touch device optimizations */
        @media (hover: none) {
            .thought-entry:hover .timeline-dot {
                transform: rotate(45deg) scale(1);
                box-shadow: none;
            }
        }

        #main-nav {

            background: rgba(10, 10, 10, 0.8);
        }
    </style>
</head>

<body>
    <!-- Fixed TRANSPARENT Navigation Bar -->
    <nav id="main-nav">
        <a href="index.html" class="cinzel">ECHOTONGUE</a>
        <ul class="nav-links" id="nav-links">
            <li><a href="index.html">Home</a></li>
            <li class="has-dropdown">
                <a href="javascript:void(0)">About <i class="fas fa-chevron-down" style="font-size: 0.9rem; "></i></a>
                <div class="dropdown">
                    <a href="index.html #about">About the book</a>
                    <a href="index.html #world">What’s inside?</a>
                    <a href="index.html #universe">The universe</a>
                    <a href="index.html #preview">Edition features</a>
                    <a href="index.html #magic">Dialects</a>
                    <a href="index.html #characters"> Main Characters</a>
                </div>
            </li>
            <li><a href="index.html #author">Author</a></li>
            <li><a href="blog.html">Blog</a></li>
            <li><a href="Timeline.html">Timeline</a></li>
            <li><a href="chaptermap.html">Chapter Map</a></li>
        </ul>
        <div class="nav-actions">
            <a href="purchasebook.html" class="btn-nav">
                <i class="fas fa-book-open"></i> &nbsp;&nbsp; <span> Purchase Book</span>
            </a>
            <button class="mobile-menu-btn" id="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>
    <!-- Mobile Navigation - Hidden by default -->
    <div class="mobile-nav" id="mobile-nav" style="margin-top: 50px; float: right;">
        <ul>
            <li><a href="index.html">Home</a></li>
            <li><a href="index.html #about">About the book</a></li>
            <li><a href="index.html #author">Author</a></li>
            <li><a href="blog.html">Blog</a></li>
            <li><a href="Timeline.html">Timeline</a></li>
            <li><a href="chaptermap.html">Chapter Map</a></li>
            <br>
            <li><a href="purchasebook.html" class="btn-nav" style="color: white;"> <i class="fas fa-book-open"></i>
                    Purchase Book</a></li>
        </ul>
    </div>
    <!-- Scroll to Top Button -->
    <button class="scroll-top" id="scroll-top">
        <i class="fas fa-chevron-up"></i>
    </button>
    <!-- Custom Cursor Elements -->
    <div class="cursor-dot"></div>
    <div class="cursor-outline"></div>



    <!-- Author's Thoughts Feed -->
    <div class="authors-thoughts">
        <div class="thoughts-container">
            <!-- Header -->
            <div class="thoughts-header">
                <h1 class="author-name">Author's Corner</h1>
                <div class="liner"></div><br>
                <p class="author-bio">
                    Sharing glimpses from the writing desk, moments of inspiration, and reflections on the creative
                    journey. <br>
                    Each thought is a snapshot from the quiet hours where stories are born.
                </p>
            </div>
            <!-- Timeline -->
            <div class="timeline-wrapper">
                <div class="timeline-line"></div>
                <div class="timeline-progress" id="timelineProgress"></div>

               
                <!-- <div class="thoughts-feed" id="thoughtsFeed"></div> -->
                 <?php
        // Database connection
        $host = 'localhost';
        $username = 'root';
        $password = '';
        $database = 'echotongue';

        $conn = new mysqli($host, $username, $password, $database);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        // Retrieve images from the database
        $sql = "SELECT * FROM authors_thoughts ";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $image_id = $row['id'];
                $thought_date = $row['thought_date'];
                $thought_text = $row['thought_text'];

                // Display the image
                 echo '<span class="new-indicator">Latest</span>
                    <div class="timeline-hit-area"></div>
                    <div class="timeline-container"> <div class="timeline-dot"></div></div>
                    <div class="thought-content">
                        <div class="thought-date">
                            <i class="far fa-clock"></i>'.$thought_date .'
                        </div>
                        <p class="thought-text">'.$thought_text .'</p>
                         
                    </div>';

               
            }
        } else {
            echo "No images found.";
        }

        $conn->close();
        ?>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <div class="footer-container">
                <div class="footer-col">
                    <h3>Echotongue</h3>
                    <p style="color: #aaa;">The epic space fantasy novel by Hermona Zeleke. Journey across thirty-two
                        planets in a universe where language is power and ancient secrets await discovery.</p>
                </div>
                <div class="footer-col">
                    <h3>Explore</h3>
                    <ul class="footer-links">
                        <li><a href="index.html">Home</a></li>
                        <li><a href="#about">About the book </a></li>
                        <li><a href="blog.html">Blog</a></li>
                        <li><a href="Timeline.html">Timeline</a></li>
                        <li><a href="chaptermap.html">Chapter Map</a></li>
                        <li><a href="purchasebook.html">Buy book</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h3>Legal</h3>
                    <ul class="footer-links">
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">Copyright</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h3>Connect</h3>
                    <ul class="footer-links">
                        <li><a href="#">Telegram</a></li>
                        <li><a href="#">Instagram</a></li>
                        <li><a href="#">Whatsapp</a></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; 2025 Hermona Zeleke. ECHOTONGUE: The Zureyan Tablets is a work of fiction. All rights
                    reserved.</p>
                <p style="margin-top: 10px; font-size: 0.8rem;"> <strong>ISBN(PB):</strong> 979-8-89604-275-4 &nbsp; |
                    &nbsp; <strong>ISBN(HB): </strong> 979-8-89604-276-1</p>
                <p> Designed by <a
                        style="font-family: 'Cinzel Decorative', serif; font-weight: 800;text-decoration: none; color:#d1cece; cursor: pointer;"
                        href="https://yonikass.netlify.app/" target="_blank">Yonatan Kassahun</a></p>
            </div>
        </div>
    </footer>
    <script>
        // Thoughts data
        const thoughtsData = [
            {
                date: "April 20, 10:22 AM",
                text: "Just finished a scene in a hidden library. 📚✨ Honestly, I could practically smell the aged paper and dust while typing. There’s something so haunting about rooms full of forgotten secrets... can’t wait for you guys to step inside. ",
                isNew: true
            },
            {
                date: "April 9, 10:22 AM",
                text: "It’s pouring outside, which is the perfect vibe for this chapter. 🌧️ Sometimes the weather just knows exactly what the story needs. This book is getting a lot darker than I originally planned, but I’m kind of loving it. 🖤",
            },
            {
                date: "April 5, 10:22 AM",
                text: "Ugh, the 'delete' key was my best friend today. Axed 2,000 words. ✍️✂️ It’s painful to cut that much, but if it doesn't make the world of Aster feel real, it has to go. Quality over quantity, right?",
            },
            {
                date: "April 3, 3:45 PM",
                text: "Update: I’ve made three cups of tea and forgotten to drink every single one. ☕️ I got so lost in a dialogue sequence that the real world just kind of... stopped existing. Writing is a trip, man.",
            },
            {
                date: "April 1, 8:30 AM",
                text: "Starting a brand new chapter today! 🌲 It feels like standing at the edge of a deep, foggy forest. I know there are paths hidden in there, but I have to find them one step at a time. Here goes nothing! 🤞",
            },
            {
                date: "March 28, 6:15 PM",
                text: "Funny how characters work—a side character I wrote for one tiny scene just decided they actually deserve a starring role. 🤷‍♂️ I’m officially no longer the boss of this book; the characters are definitely in charge now.",
            }
        ];
        // Render thoughts with staggered animation
        function renderThoughts() {
            const thoughtsFeed = document.getElementById('thoughtsFeed');
            thoughtsData.forEach((thought, index) => {
                const thoughtElement = document.createElement('div');
                thoughtElement.className = 'thought-entry';
                thoughtElement.style.animationDelay = `${index * 0.15}s`;
                thoughtElement.dataset.index = index;
                thoughtElement.innerHTML = `
                    ${thought.isNew ? '<span class="new-indicator">Latest</span>' : ''}
                    <div class="timeline-hit-area"></div>
                    <div class="timeline-container"> <div class="timeline-dot"></div></div>
                    <div class="thought-content">
                        <div class="thought-date">
                            <i class="far fa-clock"></i> ${thought.date}
                        </div>
                        <p class="thought-text">${thought.text}</p>
                         
                    </div>
                `;
                // Add hover effect to timeline line
                const hitArea = thoughtElement.querySelector('.timeline-hit-area');
                const timelineLine = document.getElementById('timelineLine');
                hitArea.addEventListener('mouseenter', function () {
                    const dot = thoughtElement.querySelector('.timeline-dot');
                    if (dot) {
                        // Highlight the specific section of the timeline
                        const rect = thoughtElement.getBoundingClientRect();
                        const timelineRect = timelineLine.getBoundingClientRect();
                        const position = ((rect.top - timelineRect.top) / timelineRect.height) * 100;
                        // Create a gradient that highlights the current position
                        timelineLine.style.background = `linear-gradient(to bottom, 
                            transparent 0%,
                            var(--secondary-color) ${Math.max(0, position - 5)}%,
                            var(--accent-color) ${position}%,
                            var(--secondary-color) ${Math.min(100, position + 5)}%,
                            transparent 100%)`;
                    }
                });
                hitArea.addEventListener('mouseleave', function () {
                    // Reset timeline to original gradient
                    setTimeout(() => {
                        timelineLine.style.background = `linear-gradient(to bottom, 
                            transparent 0%,
                            var(--secondary-color) 10%,
                            var(--primary-color) 50%,
                            var(--secondary-color) 90%,
                            transparent 100%)`;
                    }, 100);
                });
                // Add click handler for touch devices
                thoughtElement.addEventListener('click', function (e) {
                    if (!e.target.closest('a')) {
                        const content = this.querySelector('.thought-content');
                        content.style.transform = 'scale(0.98)';
                        setTimeout(() => {
                            content.style.transform = '';
                        }, 200);
                        // Mark as read
                        const indicator = this.querySelector('.new-indicator');
                        if (indicator && thought.isNew) {
                            indicator.style.opacity = '0.5';
                            indicator.textContent = 'READ';
                            setTimeout(() => {
                                indicator.style.display = 'none';
                            }, 1000);
                        }
                        // Trigger a ripple effect on the timeline dot
                        const dot = this.querySelector('.timeline-dot');
                        if (dot) {
                            dot.style.boxShadow = '0 0 0 8px rgba(139, 90, 43, 0.3)';
                            setTimeout(() => {
                                dot.style.boxShadow = '';
                            }, 500);
                        }
                    }
                });
                thoughtsFeed.appendChild(thoughtElement);
            });
        }
        // Initialize on load
        document.addEventListener('DOMContentLoaded', renderThoughts);
        // Update timeline height dynamically
        function updateTimelineHeight() {
            const timeline = document.getElementById('timelineLine');
            const feed = document.querySelector('.thoughts-feed');
            if (timeline && feed) {
                const feedHeight = feed.offsetHeight;
                timeline.style.height = `${feedHeight + 50}px`;
            }
        }
        // Initialize timeline and update on resize
        window.addEventListener('load', () => {
            updateTimelineHeight();
            // Add initial animation to timeline
            const timeline = document.getElementById('timelineLine');
            if (timeline) {
                timeline.style.opacity = '0';
                setTimeout(() => {
                    timeline.style.transition = 'opacity 0.8s ease, height 0.3s ease';
                    timeline.style.opacity = '1';
                }, 300);
            }
        });
        window.addEventListener('resize', updateTimelineHeight);
        // Add smooth scroll for navigation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        // Update timeline height after all thoughts are loaded
        setTimeout(updateTimelineHeight, 500);
    </script>
    <script src="assets/js/script.js"></script>
</body>

</html>