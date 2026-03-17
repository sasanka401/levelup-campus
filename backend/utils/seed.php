<?php
/**
 * SEED SCRIPT
 * Run from terminal: php utils/seed.php
 * Populates: 7 Levels, 40+ Tasks, 15 Badges, 1 Admin user
 * WARNING: Clears existing levels, tasks, badges before seeding.
 */

require_once __DIR__ . '/../config/env.example.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

echo "🌱 Starting database seed...\n\n";

// ─── Clear existing seed data ─────────────────────────────────
$db->exec("SET FOREIGN_KEY_CHECKS = 0");
$db->exec("TRUNCATE TABLE user_completed_tasks");
$db->exec("TRUNCATE TABLE user_badges");
$db->exec("TRUNCATE TABLE tasks");
$db->exec("TRUNCATE TABLE levels");
$db->exec("TRUNCATE TABLE badges");
$db->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "🗑️  Cleared existing levels, tasks, badges\n";

// ─── Levels ───────────────────────────────────────────────────
$levels = [
    [1, 'Programming Basics',     'Learn your first programming language and write foundational programs.',              500,   '💻', '#4CAF50'],
    [2, 'DSA Foundations',        'Master Data Structures and Algorithms — the core of technical interviews.',           1500,  '🧩', '#2196F3'],
    [3, 'First Project',          'Build, deploy, and showcase your first real-world software project.',                 3000,  '🚀', '#9C27B0'],
    [4, 'Resume & LinkedIn',      'Create a professional resume and online presence that gets noticed.',                 4000,  '📄', '#FF9800'],
    [5, 'Interview Preparation',  'Sharpen DSA skills, behavioral answers, and mock interview practice.',               6000,  '🎯', '#F44336'],
    [6, 'Applying for Jobs',      'Start actively applying to companies and tracking applications.',                     7500,  '📬', '#009688'],
    [7, 'Placement Ready',        'You are fully prepared. Land your dream offer!',                                      99999, '🏆', '#FFD700'],
];

$lvlStmt = $db->prepare("INSERT INTO levels (level_number, title, description, xp_required, icon_emoji, color) VALUES (?,?,?,?,?,?)");
foreach ($levels as $l) $lvlStmt->execute($l);
echo "✅ Seeded " . count($levels) . " levels\n";

// ─── Tasks ────────────────────────────────────────────────────
$tasks = [
    // Level 1
    [1,'Choose a programming language','Pick Python, Java, or C++ as your primary language.',30,'reading','',1],
    [1,'Install dev environment','Install VS Code + runtime. Write and run your first Hello World.',50,'coding','',2],
    [1,'Complete variables & data types module','Study variables, data types, operators. Write 5 programs.',60,'coding','',3],
    [1,'Loops & conditionals — write 10 programs','Master if/else, for, while loops. Complete 10 exercises.',80,'coding','',4],
    [1,'Functions & recursion basics','Understand functions, return values. Implement factorial recursively.',80,'coding','',5],
    [1,'Complete a free beginner course','Finish CS50, freeCodeCamp, or Codecademy basics track.',100,'reading','https://cs50.harvard.edu/x/',6],
    [1,'Solve 10 beginner problems','Solve 10 easy problems on HackerRank or Codeforces.',100,'practice','https://www.hackerrank.com/domains/tutorials/30-days-of-code',7],
    // Level 2
    [2,'Arrays & Strings mastery','Study array operations, 2-pointer, sliding window. Solve 10 problems.',100,'coding','',1],
    [2,'Linked Lists — all types','Implement singly, doubly, circular linked lists. Solve 8 LeetCode problems.',100,'coding','',2],
    [2,'Stacks & Queues','Implement using arrays and linked lists. Solve balanced brackets problem.',100,'coding','',3],
    [2,'Trees & Binary Search Trees','Implement BST. Practice BFS, DFS, level-order traversal.',120,'coding','',4],
    [2,'Sorting algorithms (implement all)','Implement Bubble, Merge Sort, and Quick Sort from scratch.',120,'coding','',5],
    [2,'Hashing & HashMaps','Study hash tables. Solve 10 problems using HashMap pattern.',100,'coding','',6],
    [2,'Solve 30 LeetCode Easy problems','30 Easy problems across arrays, strings, linked lists.',200,'practice','https://leetcode.com/problemset/',7],
    [2,'Learn Big-O notation','Understand time and space complexity analysis.',80,'reading','https://www.bigocheatsheet.com/',8],
    // Level 3
    [3,'Decide your project idea','Choose a project with real value: to-do app, expense tracker, etc.',50,'other','',1],
    [3,'Learn HTML CSS JavaScript basics','Complete a short HTML/CSS/JS crash course.',100,'reading','https://www.theodinproject.com/',2],
    [3,'Set up GitHub repo','Create repo, initialize with README and .gitignore. First commit.',50,'other','',3],
    [3,'Build the frontend/UI','Complete the user interface. Should be functional and clean.',150,'project','',4],
    [3,'Build the backend/logic','Implement all backend logic, API routes, or core algorithms.',150,'project','',5],
    [3,'Connect to a database','Store data persistently using MySQL or Firebase. Test CRUD.',120,'project','',6],
    [3,'Deploy your project','Deploy live using free hosting. Share the live URL.',150,'project','',7],
    [3,'Write a comprehensive README','Include description, features, tech stack, setup, screenshots, live link.',80,'other','',8],
    // Level 4
    [4,'Study resume best practices','Read articles on developer resumes. Study templates from Overleaf.',50,'reading','',1],
    [4,'Create your resume (1 page)','Build a clean 1-page resume: skills, projects, education.',150,'resume','',2],
    [4,'Get resume reviewed by a mentor','Share resume with a senior student. Implement their feedback.',100,'resume','',3],
    [4,'Set up LinkedIn profile','Create professional LinkedIn with photo, headline, projects, skills.',100,'resume','',4],
    [4,'Add your project to LinkedIn & GitHub','Pin project on GitHub. Add to LinkedIn Projects section.',80,'resume','',5],
    [4,'Get 50+ LinkedIn connections','Connect with classmates, seniors, alumni, recruiters.',70,'resume','',6],
    // Level 5
    [5,'Learn Dynamic Programming basics','Study memoization and tabulation. Solve 15 DP problems.',150,'coding','',1],
    [5,'Graph algorithms','Study BFS, DFS, Dijkstra, topological sort. Solve 10 graph problems.',150,'coding','',2],
    [5,'Solve 50 LeetCode Medium problems','Most important milestone. Focus on arrays, DP, graphs, trees.',300,'practice','https://leetcode.com/problemset/?difficulty=MEDIUM',3],
    [5,'Prepare 20 behavioral questions','Use STAR method for common behavioral interview questions.',100,'interview','',4],
    [5,'Do 3 mock technical interviews','Practice with a peer or mentor. 2 DSA problems in 45 minutes.',200,'interview','',5],
    [5,'Study system design basics','Learn scalability, load balancers, caching, REST vs GraphQL.',100,'reading','',6],
    [5,'Learn CS fundamentals (OS, DBMS, CN)','Study OS, DBMS, computer networks for non-coding rounds.',100,'reading','',7],
    // Level 6
    [6,'Research target companies','Create a list of 30 companies. Research hiring process and culture.',80,'other','',1],
    [6,'Apply to 20 companies','Submit applications via LinkedIn, Naukri, company websites.',200,'other','',2],
    [6,'Attend first campus placement drive','Appear in your first company drive for real experience.',150,'interview','',3],
    [6,'Track all applications in a spreadsheet','Maintain sheet with company, role, date, status, next steps.',70,'other','',4],
    [6,'Crack an online assessment (OA)','Successfully pass the online coding test of at least one company.',150,'practice','',5],
    [6,'Clear a technical interview round','Complete at least one technical interview round successfully.',200,'interview','',6],
    // Level 7
    [7,'Complete all previous 6 levels','Ensure all prior level milestones are marked complete.',200,'other','',1],
    [7,'Receive a job offer or internship','Land your first offer — full-time, internship, or PPO! 🎉',500,'other','',2],
    [7,'Help a junior student','Give back by mentoring someone in Level 1–3.',100,'other','',3],
];

$taskStmt = $db->prepare("INSERT INTO tasks (level_number, title, description, xp_reward, task_type, resource_url, sort_order) VALUES (?,?,?,?,?,?,?)");
foreach ($tasks as $t) $taskStmt->execute($t);
echo "✅ Seeded " . count($tasks) . " tasks\n";

// ─── Badges ───────────────────────────────────────────────────
$badges = [
    ['First Step',        'Complete your very first task',             '👶', 'task_count', 1,     'common'],
    ['Getting Warmed Up', 'Complete 5 tasks',                          '🌟', 'task_count', 5,     'common'],
    ['XP Collector',      'Earn 500 total XP',                         '💰', 'xp',         500,   'common'],
    ['Level Up!',         'Reach Level 2',                             '⬆️', 'level',       2,     'common'],
    ['On Fire',           'Maintain a 3-day streak',                   '🔥', 'streak',      3,     'common'],
    ['Week Warrior',      'Maintain a 7-day streak',                   '⚔️', 'streak',      7,     'rare'],
    ['DSA Warrior',       'Reach Level 2 — DSA Foundations',           '🧩', 'level',       2,     'rare'],
    ['Builder',           'Reach Level 3 — start your first project',  '🏗️', 'level',       3,     'rare'],
    ['XP Hoarder',        'Earn 2000 total XP',                        '💎', 'xp',         2000,  'rare'],
    ['Task Machine',      'Complete 20 tasks',                         '🤖', 'task_count', 20,    'rare'],
    ['Half Way There',    'Reach Level 4',                             '🎯', 'level',       4,     'epic'],
    ['Interview Ready',   'Reach Level 5 — Interview Preparation',     '💼', 'level',       5,     'epic'],
    ['Month Master',      'Maintain a 30-day streak',                  '📅', 'streak',      30,    'epic'],
    ['XP Legend',         'Earn 10,000 total XP',                      '👑', 'xp',         10000, 'epic'],
    ['Placement Ready',   'Complete all 7 levels!',                    '🏆', 'level',       7,     'legendary'],
];

$badgeStmt = $db->prepare("INSERT INTO badges (name, description, icon_emoji, trigger_type, trigger_value, rarity) VALUES (?,?,?,?,?,?)");
foreach ($badges as $b) $badgeStmt->execute($b);
echo "✅ Seeded " . count($badges) . " badges\n";

// ─── Admin user ───────────────────────────────────────────────
$checkAdmin = $db->prepare("SELECT id FROM users WHERE email = 'admin@platform.com' LIMIT 1");
$checkAdmin->execute();
if (!$checkAdmin->fetch()) {
    $hash = password_hash('Admin@123456', PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare("
        INSERT INTO users (name, email, password_hash, college, branch, graduation_year, role)
        VALUES ('Platform Admin', 'admin@platform.com', ?, 'Admin', 'CSE', 2025, 'admin')
    ")->execute([$hash]);
    echo "✅ Admin user created — email: admin@platform.com  password: Admin@123456\n";
} else {
    echo "ℹ️  Admin user already exists, skipped.\n";
}

echo "\n🎉 Database seeded successfully!\n";
echo "📋 Run schema.sql first if you haven't set up the tables yet.\n";
