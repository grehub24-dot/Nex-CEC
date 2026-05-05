TABLE OF CONTENTS
CHAPTER ONE: INTRODUCTION
1.1 Background of the Study
1.2 Problem Statement
1.3 Research Aims and Objectives
1.4 Research Questions
1.5 Scope and Delimitations
1.6 Significance of the Study
1.7 Structure of the Report
CHAPTER TWO: LITERATURE REVIEW
2.1 The Traditional Paper-Based Fee Ecosystem in Tertiary Education
2.2 The Receipt Loss Landscape: Typologies and Root Causes
2.3 Theoretical Foundations of Digital Archiving and Automation
2.4 Database Management Systems in Educational Administration
2.5 Electronic Document Generation and Delivery Mechanisms
2.6 Security Frameworks for Protecting Student Financial Data
2.7 Synthesis of Literature and Identified Research Gap
CHAPTER THREE: RESEARCH METHODOLOGY
3.1 Research Philosophy and Design Strategy
3.2 Data Collection Methods: Survey and Analysis
3.3 System Architecture and Design Framework
3.4 Database Schema Design and Normalization
3.5 System Implementation and Feature Development Strategy
3.6 Security Implementation and Access Control Protocols
3.7 Ethical Considerations in System Development
3.8 Methodological Limitations and Constraints
CHAPTER FOUR: DATA ANALYSIS AND SYSTEM DEVELOPMENT
4.1 Analysis of Survey Results: Understanding Receipt Loss
4.2 Detailed System Requirements and Feature Specification
4.3 Database Implementation and Entity Relationships
4.4 Module Development: Authentication and User Registration
4.5 Module Development: Payment Processing and Digital Archiving
4.6 Integration of Advanced Features and Automation
CHAPTER FIVE: RESULTS AND DISCUSSION
5.1 System Interface and Functionality Results
5.2 Performance Testing and Verification Metrics
5.3 Discussion of Impact on Administrative Conflicts
CHAPTER SIX: CONCLUSIONS AND RECOMMENDATIONS
6.1 Summary of Key Findings
6.2 Contributions to Knowledge and Practice
6.3 Recommendations for Stakeholders and Implementation
6.4 Limitations of the Study
6.5 Directions for Future Research
REFERENCES
APPENDICES
Appendix A: Survey Questionnaire Instrument
Appendix B: Database Structure and SQL Schema
Appendix C: System Flowchart and Process Description
ABSTRACT
School dues payment receipts serve as the primary legal proof of financial clearance for
students in tertiary institutions. However, the current reliance on paper-based receipting
systems poses a significant operational challenge, as many Information Technology (IT)
students misplace, damage, or lose these receipts over the course of their four-year
academic program. This research investigates the root causes of receipt loss and designs a
Digital School Dues Management and Receipt Archiving System to mitigate the resulting
conflicts and administrative bottlenecks during graduation. The research questions
focused on identifying the behavioral and environmental reasons why students lose
receipts, determining how a digital system can improve record retention, and evaluating
the e􀆯ectiveness of a web-based archiving solution. These questions are critically
important because the inability to prove payment leads to administrative "collisions,"
significant delays in graduation clearance, and unnecessary stress for both students and
faculty.
To answer the research questions, the study adopted a mixed-methods approach
combining qualitative survey analysis and quantitative system development. A
comprehensive survey was conducted among IT students to determine the root causes of
receipt loss. Utilizing a Design Science Research (DSR) approach, a robust web-based
system was developed using HTML5, CSS3, JAVASCRIPT and MySQL technologies. The
system implements comprehensive features including secure user authentication,
automatic PDF receipt generation, instant email notifications, and a secure relational
database for long-term archiving.
The findings revealed that the primary causes of receipt loss are not merely carelessness
but are structural issues including physical damage (torn or faded thermal paper),
misplacement during frequent hostel relocations, and the inherent di􀆯iculty of storing
small paper documents for a four-year duration. The developed system successfully
addressed these issues by replacing fragile physical receipts with immutable digital copies
stored in a secure database. The system allows students to log in and retrieve their full
payment history at any time, while administrators can instantly verify payments using a
high-speed search module. The results indicate that digital archiving eliminates the risk of
physical loss and significantly reduces the time and labor required for graduation
clearance.
The study concludes that a digital dues management system provides a robust, scalable,
and user-friendly solution to the persistent problem of missing receipts. It fosters trust
between the student body and the administration and ensures that financial records are
permanently accessible. The research recommends that the university department adopt
this system to modernize fee management, eliminate administrative disputes, and align
with global best practices in educational technology.
CHAPTER ONE
INTRODUCTION
1.1 Background of the Study
In tertiary institutions globally, and specifically within the University of Skills Training and
Entrepreneurial Development, the payment of school dues is a mandatory prerequisite for
academic progression. Upon the completion of a payment transaction, students are
traditionally issued paper receipts as the sole proof of transaction. For IT students and
other undergraduates, these receipts act as a critical financial ledger that must be
preserved for the entire duration of their study, typically spanning a minimum of four years.
At the end of this academic journey, during the final year or graduation period, students are
required to present these accumulated receipts to defend that they have fulfilled their
financial obligations. This process, known as clearance, is a high-stakes administrative
checkpoint. While this paper-based system has been the operational standard for
decades, it is increasingly becoming obsolete and ine􀆯icient in the digital age. The physical
nature of paper receipts renders them susceptible to a wide range of environmental
factors, loss, and irreversible damage. As academic programs become increasingly
digitized, the reliance on fragile physical documents for critical verification processes
creates a significant bottleneck in the administrative workflow, leading to ine􀆯iciencies
that modern educational technology can easily resolve.
1.2 Problem Statement
The main problem identified in this study is that a significant number of IT students
misplace or lose their dues payment receipts during their four-year tenure. This is not an
isolated incident but a recurring systemic issue. When the time arrives to defend their
payments or undergo graduation clearance, these students find themselves unable to
provide the necessary physical proof of payment.
This inability to produce receipts brings about a lot of "collision" severe conflict and
argument between the students and the university administration. The administration is
often forced to manually search through old, disorganized, and physical record books to
verify a claim, a process that is exhaustive, time-consuming, and often inconclusive.
Consequently, students face the prospect of delayed graduation, the frustration of being
accused of non-payment despite having paid, and the emotional stress associated with
administrative disputes. The current paper-based system fails to provide a reliable, longterm
mechanism for archiving financial proof, leading to a breakdown in trust, ine􀆯iciency
in the department, and a negative impact on the student experience.
1.3 Research Objectives
The main objective of this study is to design and implement a Digital School Dues
Management and Receipt Archiving System that ensures permanent storage, security, and
easy retrieval of payment records.
Specific objectives include:
1. To conduct a comprehensive survey to identify the common behavioral and
environmental reasons why students misplace their receipts.
2. To design a secure, normalized database schema for e􀆯iciently storing student bio-data
and payment history.
3. To develop a web-based system featuring automatic PDF receipt generation, email
notification, and user dashboards.
4. To implement robust search and verification modules to assist administrators in
resolving payment disputes instantly.
5. To evaluate the system’s ability to solve the problem of receipt loss and improve
administrative e􀆯iciency.
1.4 Research Questions
The study is guided by the following research questions:
1. Why do students frequently lose or misplace their paper receipts over a four-year
period?
2. How can a digital system improve the storage, access, and verification of payment
records compared to manual methods?
3. What specific features are essential for a digital school dues management system to
ensure security, usability, and reliability?
4. How e􀆯ective is the proposed system in reducing administrative conflicts and delays
during graduation clearance?
1.5 Scope and Delimitations
Geographical Focus: The research is strictly scoped to the IT Department of the University
of Skills Training and Entrepreneurial Development. While the system is scalable, the
primary user base and data context are derived from this specific department.
Functional Scope: The system covers the full lifecycle of fee recording, including the
recording of payments by administrators, generation of digital receipts (PDF), email
notifications, a student dashboard for history retrieval, and a comprehensive admin
dashboard for verification and reporting. It does not initially process real-time card
payments but focuses on the recording and archiving of payments made via cash, Mobile
Money (Momo), or bank drafts.
Technical Scope: The system is a web-based application developed using PHP (Hypertext
Preprocessor) for server-side logic, MySQL for database management, and
HTML/CSS/JavaScript for the frontend interface. Temporal Scope: The system is designed
to archive records for a minimum of four years and beyond, e􀆯ectively covering the entire
student lifecycle and ensuring data persistence for alumni records.
1.6 Significance of the Study
This research is significant for several key stakeholders. For students, it relieves them of the
burden of keeping physical paper for years, ensuring they can always prove payment with a
few clicks, thereby reducing anxiety. For the administration, it eliminates the tedious, errorprone
task of manual filing and searching, thereby speeding up clearance processes and
freeing up sta􀆯 for more productive tasks. For the academic institution, it contributes to the
university's digitization agenda by replacing outdated paper workflows with e􀆯icient,
secure, and environmentally friendly digital solutions.
1.7 Structure of the Report
This project report is organized into six distinct chapters to provide a logical flow of
information. Chapter One introduces the problem, background, and objectives. Chapter
Two reviews existing literature on paper-based and digital fee systems, establishing a
theoretical foundation. Chapter Three details the research methodology, system
architecture, and design strategy. Chapter Four presents the analysis of survey data and
the technical system development process. Chapter Five discusses the results,
functionality, and performance of the implemented system. Chapter Six provides
conclusions, recommendations, and directions for future research.
CHAPTER TWO
LITERATURE REVIEW
2.1 The Traditional Paper-Based Fee Ecosystem in Tertiary Education
The traditional fee management ecosystem in many educational institutions relies heavily
on physical documentation as the primary mode of transaction verification. When a
student pays fees, a cashier manually records the transaction in a physical ledger or bound
book and simultaneously issues a carbon-copy or thermal paper receipt to the student.
The onus of safekeeping is immediately transferred to the student, who is expected to
preserve this document for several years.
Studies indicate that this ecosystem is fundamentally fragile. Thermal paper receipts,
which are cost-e􀆯ective for institutions, are chemically treated and fade within months due
to ambient heat exposure or friction. Carbon copies, while more durable, become illegible
with repeated handling and are susceptible to moisture damage. Furthermore, paper is
easily damaged by water, fire, or insects. The reliance on the student’s organizational skills
and personal storage conditions for document retention creates a single point of failure in
the financial verification process, which is unacceptable for critical academic milestones
like graduation.
2.2 The Receipt Loss Landscape: Typologies and Causes
An analysis of student behavior and environmental factors reveals several distinct
typologies of receipt loss. Physical misplacement is a primary cause; students often move
hostels, change accommodation, or relocate frequently during holidays. During these
chaotic transitions, small paper receipts are easily discarded, left behind, or lost in the
shu􀆯le of belongings.
Environmental degradation is another significant factor. In tropical climates with high
humidity, paper documents are prone to mold and mildew. Ink, whether from a pen or a
printer, can bleed or fade over time. Administrative bottlenecks also contribute to the
problem; when receipts are lost, the administrative process of verifying payment involves
searching through of physical books, a method that is prone to human error, missing pages,
and illegible handwriting. A preliminary survey suggests that the sheer duration of retention
required (4 years) is a primary factor; students rarely maintain a filing system robust
enough to preserve small, unimportant-looking documents for such a long period.
2.3 Theoretical Foundations of Digital Archiving and Automation
The shift from paper to digital systems is strongly supported by the Technology Acceptance
Model (TAM), developed by Fred Davis. TAM posits that users accept new technology if it is
perceived as useful and easy to use. In the context of this research, "Usefulness" is defined
as the guarantee that a digital receipt cannot be physically lost, torn, or faded. It provides
permanent, immutable proof of payment. "Ease of Use" is defined as the ability to retrieve
the receipt via a smartphone or laptop in seconds, rather than searching through a pile of
papers.
Additionally, Design Science Research (DSR) provides the theoretical framework for
building the IT artifact. DSR emphasizes the creation and evaluation of innovative IT
artifacts intended to solve identified organizational problems. This aligns perfectly with the
goal of building a system to stop the "collision" over lost fees. The theory guides the
development not just as a coding exercise, but as a solution to a human and organizational
problem.
2.4 Database Management Systems in Educational Administration
Database Management Systems (DBMS), particularly relational databases like MySQL, are
the industry standard for digital archiving. Unlike physical files, which occupy physical
space and degrade over time, a DBMS allows for e􀆯icient digital storage with several key
advantages. ACID (Atomicity, Consistency, Isolation, Durability) Compliance ensures that
once a payment is recorded and committed to the database, it is permanent and cannot be
accidentally deleted or altered due to system crashes.
Scalability is another crucial factor. The system can handle thousands of student records
without requiring additional physical o􀆯ice space for filing cabinets. Furthermore, Querying
capabilities allow administrators to search for a record using an index number in
milliseconds, a task that would take hours manually. This e􀆯iciency transforms the
clearance process from a days-long ordeal into a rapid verification task.
2.5 Electronic Document Generation and Delivery Mechanisms
Modern systems utilize libraries like FPDF or TCPDF to generate Portable Document Format
(PDF) files dynamically on the server side. This ensures that the digital receipt looks o􀆯icial,
standardized, and cannot be altered easily by the student, preserving the integrity of the
financial document. The PDF format is universally accepted and can be opened on any
device.
Furthermore, integrating email protocols (SMTP And API Keys) allows for the immediate
delivery of the receipt to the student's email inbox. This creates a cloud-based backup that
is independent of the student's physical device or local storage. Even if a student loses
their laptop, their email history serves as a permanent, searchable archive of all their
financial transactions with the university.
2.6 Security Frameworks for Student Financial Data
Moving financial data online requires robust security frameworks to prevent fraud and
unauthorized access. Literature emphasizes the importance of Password Hashing (using
algorithms like Bcrypt or MD5) to protect user credentials. Storing passwords in plain text is
a critical vulnerability; hashing ensures that even if the database is compromised, the
actual passwords remain secure.
Role-Based Access Control (RBAC) is also critical. In a fee system, students must only be
able to view their own data and download their own receipts. They must not have access to
the administrative functions or the data of other students. Conversely, administrators have
system-wide access to record payments and verify records. This strict separation of duties
prevents unauthorized modification of financial records and ensures auditability.
2.7 Synthesis of Literature and Identified Research Gap
While digital fee systems exist in many universities, literature reveals that existing solutions
often focus heavily on the transaction aspect—the actual process of paying the fee via
gateways. There is a specific gap regarding "archiving for long-term defense." Many systems
generate a receipt at the moment of payment but do not prioritize the retrieval experience
years later. This research focuses specifically on the archiving and retrieval aspect to solve
the graduation clearance conflict. It posits that a system is only as good as its ability to
reliably retrieve historical data when it matters most—at the end of a student's journey.
CHAPTER THREE
RESEARCH METHODOLOGY
3.1 Research Philosophy and Design Strategy
3.1.1 Research Philosophy
This research adopts a pragmatist philosophy. Pragmatism is chosen because the study is
concerned with a practical, real-world problem—the loss of receipts—and "what works" to
solve it. It moves beyond the dichotomy of positivism (purely quantitative) and
interpretivism (purely qualitative) to allow for the combination of both. It allows for the
integration of qualitative survey data (understanding the human reasons why receipts are
lost) and quantitative technical development (building and testing the system metrics).
3.1.2 Research Design: Design Science Research
The study employs Design Science Research (DSR) as the primary methodology. The goal is
not just to observe the problem but to build and evaluate an IT artifact (the Digital School
Dues System) that solves it. The DSR process followed in this study comprises six distinct
activities: Problem Identification (recognizing the collision caused by lost receipts),
Solution Design (defining the database schema and system features), Development
(coding the web application), Demonstration (showing the system to users), Evaluation
(testing if the system retrieves 4-year-old records instantly), and Communication (this
report).
3.2 Data Collection Methods: Survey and Analysis
To understand the root causes of the problem from the user's perspective, a survey was
conducted among ICT students.
Participants: A total of 50 randomly selected students from levels 100 to 300 participated
in the survey. This sample size was chosen to provide a statistically significant snapshot of
the department without being unwieldy.
Instrument: A structured questionnaire was administered containing both closed-ended
and open-ended questions. Key questions included: "Have you ever lost a school fees
receipt?", "What was the primary cause of the loss?", and "How did this a􀆯ect your
academic administration?".
Data Analysis: Responses were collated and categorized into themes: Misplacement,
Damage (Torn/Faded), Theft, and Administrative Error. This qualitative data was then
quantified (e.g., 35% said Misplacement) to inform the design priorities of the system,
particularly the "Search and Retrieval" and "Email Backup" features.
3.3 System Architecture and Design
The system follows a Three-Tier Architecture, which is a standard for client-server
applications to ensure scalability and maintainability.
Client Tier: This is the User Interface (UI) presented in a web browser. It handles the
interaction between the user (Student or Admin) and the system, displaying forms,
dashboards, and receipts. It uses HTML5, CSS3, and Bootstrap for responsiveness.
Application Tier: This is the server-side logic. Written in PHP, it processes user requests
(e.g., login, search, save), handles business logic (e.g., calculating totals, generating
unique IDs), and acts as the intermediary between the client and the database.
Data Tier: This is the MySQL database where all student records, payment logs, and system
configurations are permanently archived. It ensures data persistence and integrity.
3.4 Database Schema Design
The database was designed to ensure data integrity, minimize redundancy, and prevent
data anomalies through normalization. It consists of three core tables:
students_table: Stores bio-data (student_id, index_number, full_name, email, programme,
level, password_hash). The index_number is unique to prevent duplicate student profiles.
payments_table: Stores transaction data (payment_id, student_id [Foreign Key], amount,
semester, academic_year, payment_method, receipt_number, payment_date). It links back
to the students table.
admin_table: Stores sta􀆯 credentials (admin_id, username, hashed_password, role). This
ensures only authorized personnel can record payments.
3.5 System Implementation and Feature Development
The system was developed using the following integrated feature set:
Authentication: A secure Login/Logout module using PHP sessions to maintain user state
across pages. Unauthorized users are redirected to the login page.
Payment Recording: An Admin-only module where the sta􀆯 inputs payment details. The
system automatically generates a unique, sequential Receipt ID (e.g., REC-2026-0054) to
prevent fraud.
PDF Generation: Uses the FPDF library to programmatically create a branded, professionallooking
receipt that includes the university logo, watermarks, and transaction details,
which is then streamed to the user's browser for download.
Email Notification: Uses PHPMailer to interface with an SMTP server (like Gmail). When a
payment is saved, the system triggers an email with the PDF attached to the student's
registered email address.
Dashboards: Separate, role-specific views. Students see a history of their own payments.
Admins see a search bar to look up any student and an overview of total collections.
3.6 Security Implementation and Access Control
To protect sensitive financial data, several security strategies were implemented:
SQL Injection Prevention: All database queries use Prepared Statements (Parameterized
queries). This sanitizes user input, preventing hackers from manipulating the database via
input fields.
Password Security: All user passwords (students and admins) are hashed using algorithms
like Bcrypt before being stored in the database. Plain text passwords are never stored.
Access Control: Middleware logic checks the user role stored in the session. Students are
explicitly blocked from accessing admin panels (e.g., /admin/record_payment) via codelevel
restrictions, not just hidden UI elements.
3.7 Ethical Considerations
The survey phase respected student anonymity; no names were collected, only levels and
experiences. For the system development phase, to comply with data protection
regulations, no real student financial data was used during testing. A set of realistic dummy
data was generated to test the archiving capabilities without violating privacy laws or
exposing sensitive real-world financial information.
3.8 Methodological Limitations
The system was tested on a local server environment (localhost/XAMPP). While it functions
correctly in this controlled setting, it may face performance challenges (latency, timeout
errors) under high concurrent user loads on a live internet server. Furthermore, the current
version records payments post-factum (manual entry by admin after receiving cash/MoMo)
rather than integrating live payment gateways (APIs) for real-time processing, which is a
limitation of the current scope.
CHAPTER FOUR
DATA ANALYSIS AND SYSTEM DEVELOPMENT
4.1 Analysis of Survey Results: Understanding Receipt Loss
The quantitative analysis of the survey data from 50 ICT students revealed a clear pattern
regarding the loss of receipts. The data was categorized into four primary causes,
highlighting the behavioral and environmental challenges of paper-based systems.
The survey results indicated that 35% of respondents cited "Misplacement/Forgetting
where I put it" as the primary cause. This suggests that the small size of receipts makes
them easy to lose among other documents. 25% cited "Faded ink or torn paper," pointing to
the poor quality of thermal paper used in many receipts. 20% cited "Lost during moving
hostels," indicating that transition periods are high-risk times for document loss. Finally,
20% cited "Theft or mixed up with friends' papers." This analysis confirms that the problem
is environmental and behavioral, reinforcing the need for a digital system that is immune to
physical loss, theft, and degradation.
4.2 Detailed System Requirements and Feature Specification
Based on the problem analysis and survey findings, the following specific functional and
non-functional requirements were engineered to guide the development:
Functional Requirements:
User Authentication: Distinct, secure login portals for Students (using Index Number) and
Administrators.
Search & Verification: A critical, high-priority feature allowing administrators to type an
Index Number and retrieve ALL payment history instantly to end arguments.
Digital Archiving: The database must be designed to retain records for >4 years without
data degradation, supporting queries for alumni.
Non-Functional Requirements:
Security: Data must be encrypted, and access must be role-based.
Reliability: The system must be available 24/7 for students to check their status.
Usability: The interface must be intuitive enough for students with low IT literacy to navigate
easily.
4.3 Database Implementation and Relationships
The database was physically implemented in MySQL using the phpMyAdmin interface. Key
relationships were established to ensure data integrity.
One-to-Many Relationship: One student in the students_table can have multiple entries in
the payments_table (one for each semester). This is enforced by a Foreign Key constraint
on student_id in the payments table.
Constraints: The index_number in the students table is set to UNIQUE to prevent duplicate
registrations of the same student. The receipt_number in the payments table is also set to
UNIQUE to prevent the issuance of duplicate receipt IDs, which would constitute financial
fraud.
4.4 Module Development: Authentication and Registration
The registration module was developed to capture comprehensive student data: Full
Name, Index Number, Department, Level, Email, and Phone Number.
Validation logic was implemented using PHP and JavaScript to ensure data quality. For
example, the email field is validated to ensure it contains an "@" symbol and a domain
name, which is crucial for the email notification feature. The login system uses PHP
sessions to keep the user logged in as they navigate di􀆯erent pages (Dashboard, History,
Profile), maintaining a persistent state of authentication.
4.5 Module Development: Payment Processing and Archiving
The Admin "Record Payment" module serves as the core business logic of the system.
Inputs: The admin selects a student (by ID), enters the Amount, Academic Year, Semester,
and Payment Method (Cash, MoMo, Bank Draft).
Process: Upon submission, the system first validates the inputs. It then inserts the data
into the payments_table. Simultaneously, it triggers the PDF generator class (FPDF) to
create a digital receipt on the fly.
Archiving: Unlike a paper file that is filed away in a cabinet and becomes di􀆯icult to find,
the database record remains active and indexed. It is "archived" in the sense that it is
permanently stored and instantly queryable. The system logs the timestamp automatically,
creating a verifiable audit trail.
4.6 Integration of Advanced Features and Automation
To make the system robust and competitive, the following advanced features were
integrated:
PDF with Digital Signature Placeholder: The generated receipt layout includes a designated
area for the Bursar’s signature or a digital stamp, making it legally recognizable.
Email Backup Automation: Every successful payment entry triggers an asynchronous email
dispatch. The email subject is dynamically formatted: "School Dues Payment Confirmation
– [Academic Year] - [Student Name]". This ensures the student has a permanent,
searchable cloud backup (Gmail/Yahoo inbox) that lasts for years, independent of their
physical possession.
Export to Excel: The admin dashboard includes a backend script to export the payment
records query result into a CSV or Excel file format. This aids in o􀆯line reporting and
auditing by the finance department.
CHAPTER FIVE
RESULTS AND DISCUSSION
5.1 System Interface and Functionality Results
The implemented system was rigorously tested for its core functionalities to ensure it met
the design specifications. The results of these tests are detailed below:
A. Student Dashboard and Access:
Upon logging in with valid credentials, the student is presented with a personalized
dashboard. The interface displays a clean, tabular listing of their payment history sorted by
date (most recent first). A prominent "Download PDF" button is placed next to each entry.
Test results confirmed that clicking this button successfully retrieves the specific receipt
for that transaction. This satisfies the objective of allowing students to retrieve receipts
anytime, independent of o􀆯ice hours.
B. Admin Verification (The "Collision" Solver):
A critical test scenario was simulated to address the problem statement. A student claims
they paid for a specific semester but lost the receipt. The Admin enters the student's Index
Number into the global search bar.
Result: The system displayed the complete payment record, including the exact date,
amount, payment method, and the specific unique Receipt ID in under 1 second.
Implication: This functionality e􀆯ectively solves the "collision." The argument is resolved
instantly with objective data. There is no need for manual searching through books or
accusing the student of lying.
C. Email Notification System:
Test payments were recorded for dummy student accounts.
Result: The designated email addresses received the PDF receipt within 30 to 60 seconds
of the admin clicking "Save."
Implication: This feature creates a safety net. Even if a student deletes the local file or loses
their laptop, their email inbox serves as a permanent, retrievable archive of their financial
obligations to the university.
5.2 Performance Testing and Verification
To ensure the system is robust, several test cases were executed. Table 5.1 summarizes the
test cases executed to validate the system against the research objectives and user
requirements.
Table: System Functionality Test Results
Test Case ID Description Expected Result Actual Result Status
TC_01 Register Student Save student details to database without errors Data saved
successfully, duplicate index number blocked PASS
TC_02 Record Payment Generate Unique Receipt ID and save transaction Receipt ID
generated (e.g., REC-2026-001), data committed PASS
TC_03 Duplicate Receipt Check Prevent saving of payment with same Receipt ID System
displayed error: "Receipt Number already exists" PASS
TC_04 Search Student Find student by Index Number in database Student record and full
history retrieved instantly (<1s) PASS
TC_05 Download PDF Trigger browser download of receipt file PDF file downloaded
successfully and opens correctly PASS
TC_06 Email Receipt Send email with PDF attachment to student Email received in inbox
with correct subject and attachment PASS
TC_07 Access Control Block student from accessing Admin page Redirected to login page
with "Access Denied" message PASS
5.3 Discussion of Impact on the "Collision" Problem
The results demonstrate that the Digital School Dues Management System e􀆯ectively
solves the problem identified in Chapter One. The transition from a paper-based to a digital
ecosystem has profound implications for the university administration.
1. Elimination of Physical Loss: Because the receipt exists as a permanent record in a
relational database and as an attachment in a cloud email service, it is immune to the
physical risks of tearing, fading, fire, or water damage. The concept of "misplacing" a digital
record is virtually non-existent if proper search functions are in place.
2. End of Arguments: The "Search & Verification" feature provides objective, indisputable
proof of payment. In a dispute, the database serves as the single source of truth. If the
database says "Paid," the argument is over. This shifts the administrative culture from
suspicion to verification.
3. Operational E􀆯iciency: The time taken for clearance is reduced from hours or days
(manual search) to seconds (digital query). This allows administrative sta􀆯 to focus on
other value-added tasks rather than filing and searching for paper.
The system e􀆯ectively shifts the burden of proof. Previously, the student had to prove they
paid by keeping a fragile paper. Now, the institution maintains the secure database, and the
student can simply access it. This creates a fairer, more transparent, and less stressful
academic environment.
CHAPTER SIX
CONCLUSIONS AND RECOMMENDATIONS
6.1 Summary of Key Findings
This research successfully addressed the pervasive issue of lost school dues receipts
within the ICT department. A comprehensive survey confirmed that students lose receipts
primarily due to misplacement, environmental damage (fading/tearing), and the logistical
challenges of moving hostels. In direct response to these findings, a robust web-based
Digital School Dues Management and Receipt Archiving System was designed and
implemented. The system successfully archives payments, generates professional PDF
receipts, and sends automated email confirmations. Rigorous testing proved that the
system can retrieve 4-year-old payment records instantly, e􀆯ectively solving the
administrative "collisions" and emotional stress that occur during graduation clearance.
6.2 Contributions to Knowledge and Practice
This project contributes a practical, cost-e􀆯ective solution to the field of educational
informatics. It demonstrates how simple, open-source web technologies (PHP/MySQL) can
be leveraged to solve persistent, seemingly mundane administrative problems that have a
high impact on user satisfaction. It also highlights the importance of "Digital Archiving" as a
specific feature distinct from "Digital Payment," emphasizing that the ability to pay online is
useless if the user cannot retrieve the proof of payment years later. The study adds to the
body of knowledge on Design Science Research by showing its applicability in small-scale
educational administrative contexts.
6.3 Recommendations for Stakeholders
Based on the success of this project, the following recommendations are made:
Departmental Adoption: The ICT Department should adopt this system immediately to
phase out paper receipts. A phased rollout is recommended, starting with first-year
students, to allow the database to populate correctly.
Mandatory Email Policy: Students should be required to provide a valid, active email
address upon registration. The email backup feature is only e􀆯ective if students monitor
their inboxes.
Regular Data Backups: The system administrator must perform regular database backups
(daily or weekly) to an external hard drive or cloud server to prevent data loss due to
hardware failure or cyberattacks.
User Training: Short training sessions should be organized for administrative sta􀆯 to ensure
they are comfortable with the digital workflow and understand the security protocols.
6.4 Limitations of the Study
The system, while functional, has certain limitations. Currently, it relies on the admin to
manually input payments after money is received at the bank or via MoMo. It does not
integrate directly with Mobile Money APIs (like MTN MoMo) or Bank gateways for real-time,
automatic payment confirmation. Additionally, the system was tested in a controlled
environment with a limited dataset. It was not tested with the full student population load
(e.g., 1000+ concurrent users), which may reveal performance bottlenecks or server
latency issues that require optimization.
6.5 Directions for Future Research
Future research should focus on expanding the capabilities of the system:
Payment Gateway Integration: Connecting the system directly to MTN Momo, Telecel Cash,
or Bank APIs to allow students to pay directly on the portal. This would automate the
recording process, eliminating human error in data entry.
SMS Notifications: Adding SMS alert capabilities for students who may not check their
email frequently, ensuring they receive immediate confirmation of payment.
Mobile Application Development: Developing a dedicated Android or iOS mobile
application. This would increase accessibility, as most students primarily use
smartphones, and would allow for push notifications regarding payment deadlines.
Biometric Verification: Integrating fingerprint or facial recognition for the admin login
process to further enhance security and prevent unauthorized access to financial records.
REFERENCES
Davis, F. D. (1989). Perceived usefulness, perceived ease of use, and user acceptance of
information technology. MIS Quarterly, 13(3), 319–340.
Hevner, A. R., March, S. T., Park, J., & Ram, S. (2004). Design science in information systems
research. MIS Quarterly, 28(1), 75–105.
Laudon, K. C., & Laudon, J. P. (2020). Management Information Systems: Managing the
Digital Firm (16th ed.). Pearson.
Ozuru, H. N., & Chukwudi, I. (2021). Design and implementation of an automated school
fee payment system. International Journal of Computer Applications, 9(2), 45-52.
Pressman, R. S., & Maxim, B. R. (2020). Software Engineering: A Practitioner's Approach
(9th ed.). McGraw-Hill Education.
APPENDICES
Appendix A: Survey Questionnaire Instrument
Department of ICT – Receipt Loss Survey
1. What is your current level of study?
[ ] 100 [ ] 200 [ ] 300 [ ] 400
2. Have you ever lost a school fees/dues receipt?
[ ] Yes [ ] No
3. If yes, what was the primary cause of the loss?
[ ] Misplaced it / Forgot where I put it
[ ] It got torn or the ink faded
[ ] Lost it while moving from one hostel to another
[ ] Theft or it got mixed up with other papers
4. Did the loss of the receipt cause any issues for you during departmental verification or
clearance?
[ ] Yes, significant delays
[ ] Yes, minor issues
[ ] No, I managed to resolve it
5. Would you prefer a digital system where you can download your receipt anytime?
[ ] Yes [ ] No [ ] Maybe
Appendix B: Database Structure and SQL Schema
-- SQL Code for creating the database tables
CREATE TABLE students (
student_id INT AUTO_INCREMENT PRIMARY KEY,
index_number VARCHAR(20) UNIQUE NOT NULL,
full_name VARCHAR(100) NOT NULL,
email VARCHAR(100) NOT NULL,
programme VARCHAR(50),
level VARCHAR(10),
phone_number VARCHAR(15),
password VARCHAR(255) NOT NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE admin (
admin_id INT AUTO_INCREMENT PRIMARY KEY,
username VARCHAR(50) UNIQUE NOT NULL,
password VARCHAR(255) NOT NULL,
role VARCHAR(20) DEFAULT 'admin'
);
CREATE TABLE payments (
payment_id INT AUTO_INCREMENT PRIMARY KEY,
student_id INT NOT NULL,
amount DECIMAL(10,2) NOT NULL,
semester VARCHAR(20) NOT NULL,
academic_year VARCHAR(10) NOT NULL,
payment_method VARCHAR(50) NOT NULL,
receipt_number VARCHAR(50) UNIQUE NOT NULL,
payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
);
Appendix C: System Flowchart and Process Description
1. Start: Student approaches the bank or cashier to pay fees.
2. Process: Payment is made via Cash, Momo, or Bank Draft.
3. Admin Action: Administrator logs into the Digital Dues System.
4. Data Entry: Admin locates the student by Index Number and enters the payment details
(Amount, Semester, Year).
5. System Action 1: System validates data and saves record to the MySQL Database.
6. System Action 2: System generates a unique Receipt ID.
7. System Action 3: System generates a PDF receipt using the FPDF library.
8. System Action 4: System sends an email with the PDF attached to the student.
9. End: Student logs in to the portal to download the receipt, or Admin uses the Search
feature to verify payment instantly