<!-- Slide number: 1 -->

DESIGN AND IMPLEMENTATION OF A DIGITAL SCHOOL DUES MANAGEMENT AND RECEIPT ARCHIVING SYSTEM
GROUP
PROGRAMME

1
BSc Information Technology Education (ITE 300E)
GROUP MEMBERS
Owusu Emmanuel
Opoku Evans
Amontira Titus Atigidiwe
Opoku Samuel
Freduah Agyemang Emmanuel
Addo Gifty
Marfo Godfred
Amankwaah David
Pokuah Sarpong Hannah
Yeboah Akwasi

<!-- Slide number: 2 -->
BACKGROUND
CONTEXT
CURRENT PRACTICE

![image.png](Picture5.jpg)

![image.png](Picture2.jpg)
School dues payment is a mandatory prerequisite for academic progression in tertiary institutions (specifically USTED)
Students are issued paper receipts as the sole proof of transaction
DURATION
THE ISSUE

![image.png](Picture8.jpg)

![image.png](Picture11.jpg)
Physical receipts must be preserved for a minimum of four years to be presented during graduation clearance
Physical nature of paper renders receipts susceptible to environmental factors, loss, and irreversible damage

<!-- Slide number: 3 -->
PROBLEMS
01
02

![image.png](Picture7.jpg)

![image.png](Picture3.jpg)
Recurring Loss
Administrative Collision
IT students lose receipts during 4-year tenure, creating repeated administrative conflicts
Arguments during clearance when students cannot provide proof of payment
03
04

![image.png](Picture15.jpg)

![image.png](Picture11.jpg)
Inefficient Verification
Severe Consequences
Manual search through disorganized physical records is exhaustive, time-consuming, and often inconclusive
Delayed graduation, accusations of non-payment despite payment, emotional stress and trust breakdown

<!-- Slide number: 4 -->
EXISTING SYSTEMS
PAPER-BASED ECOSYSTEM
FRAGILITY OF MATERIALS

![image.png](Picture2.jpg)

![image.png](Picture5.jpg)
Cashiers manually record transactions in bound books and issue carbon-copy or thermal paper receipts
Thermal paper fades within months due to heat or friction; carbon copies become illegible with handling and moisture damage
SINGLE POINT OF FAILURE
RETRIEVAL DIFFICULTY

![image.png](Picture8.jpg)

![image.png](Picture11.jpg)
Relies entirely on the student's organizational skills and storage conditions
Searching physical archives for specific transactions is prone to human error, missing pages, and illegible handwriting

<!-- Slide number: 5 -->
AIMS AND OBJECTIVES
SPECIFIC OBJECTIVES

![image.png](Picture6.jpg)
01
Conduct survey to identify behavioral/environmental reasons for receipt loss
MAIN AIM

![image.png](Picture2.jpg)
Design and implement a Digital School Dues Management and Receipt Archiving System ensuring permanent storage, security, and easy retrieval
02
Design secure normalized database schema
03
Develop web-based system with PDF generation and email/SMS notifications
04
Implement search and verification modules
Digital Transformation
05
Evaluate system effectiveness in reducing administrative conflicts

<!-- Slide number: 6 -->
METHODOLOGY - RESEARCH DESIGN
RESEARCH PHILOSOPHY
RESEARCH DESIGN

![image.png](Picture2.jpg)

![image.png](Picture6.jpg)
Pragmatism
DSR
Focuses on what works to solve the real-world problem of receipt loss
Design Science Research: Build and evaluate IT artifact to solve identified problem
DATA COLLECTION
INTEGRATION

![image.png](Picture10.jpg)

![image.png](Picture15.jpg)
50
Mixed-methods approach combining qualitative survey analysis and quantitative system development
IT students surveyed
Randomly selected from Levels 100-300 using structured questionnaire on receipt loss habits

<!-- Slide number: 7 -->
METHODOLOGY - SYSTEM ARCHITECTURE
THREE-TIER ARCHITECTURE
TOOLS & TECHNOLOGIES

![image.png](Picture2.jpg)

![image.png](Picture17.jpg)
Client-Server Model
PHP

![image.png](Picture20.jpg)
01

![image.png](Picture6.jpg)
Server-side scripting
CLIENT TIER
User Interface (HTML5, CSS3, Bootstrap) for interaction
MySQL

![image.png](Picture23.jpg)
Database management
02

![image.png](Picture10.jpg)
APPLICATION TIER
JavaScript
Server-side logic (PHP) processing requests and business logic

![image.png](Picture26.jpg)
Frontend interactivity
03

![image.png](Picture14.jpg)
FPDF

![image.png](Picture29.jpg)
DATA TIER
PDF generation library
MySQL database for permanent archiving

<!-- Slide number: 8 -->
METHODOLOGY - DATABASE & SECURITY DESIGN
SECURITY FEATURES

![image.png](Picture17.jpg)
DATABASE SCHEMA

![image.png](Picture2.jpg)
Multi-layered protection
Normalized design with core tables
01

![image.png](Picture20.jpg)
01

![image.png](Picture6.jpg)
SQL INJECTION PREVENTION
STUDENTS_TABLE
Prepared Statements implementation
Bio-data
02

![image.png](Picture24.jpg)
02

![image.png](Picture10.jpg)
PASSWORD SECURITY
PAYMENTS_TABLE
Bcrypt hashing algorithm
Transaction history
03

![image.png](Picture28.jpg)
03

![image.png](Picture14.jpg)
ACCESS CONTROL
ADMIN_TABLE
Role-Based Access Control (RBAC) separating student views from administrative functions
Staff credentials

<!-- Slide number: 9 -->
RESULTS - SURVEY ANALYSIS
Misplacement / Forgot where they put it
35%
SURVEY SAMPLE

![image.png](Picture2.jpg)
Faded ink or torn paper (Thermal paper degradation)
25%
50
Lost during moving hostels
20%
ICT Students
Levels 100-300
Theft or mixed up with friends' papers
20%
ROOT CAUSES IDENTIFIED

CONCLUSION

![image.png](Picture7.jpg)

![image.png](Picture18.jpg)
The problem is structural and environmental, confirming need for digital solution immune to physical loss

<!-- Slide number: 10 -->
RESULTS - SYSTEM FUNCTIONALITY
IMPLEMENTED FEATURES

![image.png](Picture2.jpg)
ADMIN VERIFICATION

![image.png](Picture14.jpg)
Core system capabilities
Instant record retrieval
STUDENT DASHBOARD

![image.png](Picture5.jpg)
Login access to view full payment history sorted by date
<1
second
PDF GENERATION

![image.png](Picture8.jpg)
Automatic creation of professional receipts with university logo and watermarks using FPDF library
GLOBAL SEARCH
Administrators retrieve student's full financial history using Index Number
EMAIL & SMS BACKUP

![image.png](Picture11.jpg)
Automated dispatch of PDF receipts to student emails (SMTP) and SMS confirmation (Wigal API) upon payment entry

<!-- Slide number: 11 -->
RESULTS - PERFORMANCE TESTING
TEST CASES CONDUCTED

![image.png](Picture2.jpg)
IMPACT

![image.png](Picture24.jpg)
TC_01

![image.png](Picture7.jpg)
REGISTRATION
Data saved successfully; duplicate index numbers blocked

![image.png](Picture26.jpg)
TC_02

![image.png](Picture11.jpg)
RECORDING
OBJECTIVE PROOF
Unique Receipt IDs generated correctly
The Search and Verification feature provides objective proof of payment
TC_04

![image.png](Picture15.jpg)
SEARCH
Student records retrieved instantly (<1s)
TC_06

![image.png](Picture19.jpg)
EMAIL & SMS
Effectively ending arguments regarding lost receipts
Receipts received in inbox and SMS confirmation delivered within 30-60 seconds
TC_07

![image.png](Picture23.jpg)
ACCESS CONTROL
Students successfully blocked from accessing Admin panels

<!-- Slide number: 12 -->
CONCLUSION
SUMMARY

![image.png](Picture2.jpg)
RECOMMENDATIONS

![image.png](Picture13.jpg)
Successfully addressed the issue of lost receipts by replacing fragile paper with a secure digital archiving system
Immediate departmental adoption
Mandatory student email registration for backups
IMPACT

![image.png](Picture5.jpg)
FUTURE WORK

![image.png](Picture17.jpg)
Eliminates physical loss and environmental damage

![image.png](Picture7.jpg)
Real-time Payment Gateways

![image.png](Picture19.jpg)
Integration with MTN MoMo, Banks
Reduces clearance time from hours/days to seconds

![image.png](Picture9.jpg)
Mobile Application

![image.png](Picture22.jpg)
Cross-platform mobile app development
Shifts burden of proof from student to secure institutional database

![image.png](Picture11.jpg)