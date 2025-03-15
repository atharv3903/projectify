import pymysql
import sys
from fpdf import FPDF
import os
from datetime import datetime

if len(sys.argv) < 2:
    print("Error: Project ID not provided!")
    sys.exit(1)

project_id = sys.argv[1]  

# Connect to MySQL Database on XAMPP
conn = pymysql.connect(host="localhost", user="root", password="", database="projectify", port=3307)
cursor = conn.cursor()

# Fetch project details
cursor.execute("""
    SELECT id, name, description, status, keywords, year_and_batch, git_repo_link, frozen, interested_domains, pdf_path
    FROM project 
    WHERE id = %s
""", (project_id,))

project = cursor.fetchone()

if not project:
    print(f"Error: No project found for project_id = {project_id}")
    sys.exit(1)

project_id, name, description, status, keywords, year_and_batch, git_repo_link, frozen, interested_domains, pdf_path = project

# Fetch Gantt chart data (tasks related to the project)
# cursor.execute("""
#     SELECT name, assigned_to, start, end, status
#     FROM task
#     WHERE project_id = %s
# """, (project_id,))
# Fetch Gantt chart data (tasks related to the project)
cursor.execute("""
    SELECT name, assigned_to, start, end, status
    FROM task
    WHERE project_id = 'proj_674b061eac43a6.33133935';
""")

tasks = cursor.fetchall()

# Initialize PDF
pdf = FPDF()
pdf.set_auto_page_break(auto=True, margin=15)
pdf.add_page()
pdf.set_font("Arial", size=12)

# Project Details Section
pdf.set_font("Arial", 'B', 16)
pdf.cell(200, 10, "Project Report", ln=True, align='C')
pdf.ln(10)

pdf.set_font("Arial", 'B', 12)
pdf.cell(0, 10, "Project Details:", ln=True)
pdf.set_font("Arial", size=12)
pdf.multi_cell(0, 10, 
    f"Project ID: {project_id}\n"
    f"Project Name: {name}\n"
    f"Description: {description}\n"
    f"Status: {status}\n"
    f"Keywords: {keywords}\n"
    f"Year & Batch: {year_and_batch}\n"
    f"GitHub Repo: {git_repo_link}\n"
    f"Frozen: {'Yes' if frozen else 'No'}\n"
    f"Interested Domains: {interested_domains}\n"
    f"PDF Path: {pdf_path if pdf_path else 'No PDF Uploaded'}"
)
pdf.ln(5)

# # Gantt Chart Section
# if tasks:
#     pdf.set_font("Arial", 'B', 12)
#     pdf.cell(0, 10, "Gantt Chart - Project Tasks", ln=True)
#     pdf.ln(5)
    
#     pdf.set_font("Arial", 'B', 10)
#     pdf.cell(50, 10, "Task Name", 1)
#     pdf.cell(40, 10, "Assigned To", 1)
#     pdf.cell(30, 10, "Start Date", 1)
#     pdf.cell(30, 10, "End Date", 1)
#     pdf.cell(30, 10, "Status", 1)
#     pdf.ln()

#     pdf.set_font("Arial", size=10)
#     for task in tasks:
#         task_name, assigned_to, start_date, end_date, task_status = task
#         pdf.cell(50, 10, task_name, 1)
#         pdf.cell(40, 10, assigned_to, 1)
#         pdf.cell(30, 10, str(start_date), 1)
#         pdf.cell(30, 10, str(end_date), 1)
#         pdf.cell(30, 10, task_status, 1)
#         pdf.ln()
# else:
#     pdf.set_font("Arial", size=12)
#     pdf.cell(0, 10, "No tasks available for this project.", ln=True)


# Sort tasks by assigned_to
tasks = sorted(tasks, key=lambda x: x[1])

# pdf = FPDF()
pdf.set_auto_page_break(auto=True, margin=15)
pdf.add_page()

pdf.set_font("Arial", 'B', 14)
pdf.cell(0, 10, "Gantt Chart - Project Tasks", ln=True, align='C')
pdf.ln(5)

# Table Header
pdf.set_font("Arial", 'B', 10)
pdf.cell(50, 10, "Task Name", 1, 0, 'C')
pdf.cell(40, 10, "Assigned To", 1, 0, 'C')
pdf.cell(30, 10, "Start Date", 1, 0, 'C')
pdf.cell(30, 10, "End Date", 1, 0, 'C')
pdf.cell(30, 10, "Status", 1, 1, 'C')

# Table Data
pdf.set_font("Arial", size=10)
task_timeline = []  # To store timeline bars

for task in tasks:
    task_name, assigned_to, start_date, end_date, task_status = task
    pdf.cell(50, 10, task_name, 1)
    pdf.cell(40, 10, assigned_to, 1)
    pdf.cell(30, 10, str(start_date), 1)
    pdf.cell(30, 10, str(end_date), 1)
    pdf.cell(30, 10, task_status, 1)
    pdf.ln()
    
    # Convert dates to datetime for Gantt chart representation
    start_date = datetime.strptime(str(start_date), "%Y-%m-%d")
    end_date = datetime.strptime(str(end_date), "%Y-%m-%d")
    task_timeline.append((task_name, assigned_to, start_date, end_date))

pdf.ln(10)
pdf.set_font("Arial", 'B', 12)
pdf.cell(0, 10, "Timeline Representation", ln=True, align='C')
pdf.ln(5)

# Gantt Chart Representation (Basic Bar Chart Simulation)
x_start = 20
x_end = 190
bar_height = 6
current_y = pdf.get_y()
color_map = {"failed": (255, 0, 0), "in_progress": (255, 165, 0), "complete": (0, 128, 0)}

min_date = min(task_timeline, key=lambda x: x[2])[2]
max_date = max(task_timeline, key=lambda x: x[3])[3]

for task_name, assigned_to, start, end in task_timeline:
    pdf.set_xy(x_start, current_y)
    pdf.cell(50, 6, f"{task_name} ({assigned_to})", border=1)
    
    total_days = (max_date - min_date).days
    task_start_offset = (start - min_date).days
    task_duration = (end - start).days
    
    # Calculate bar position
    bar_x = x_start + 55 + (task_start_offset / total_days) * (x_end - x_start - 60)
    bar_width = (task_duration / total_days) * (x_end - x_start - 60)
    
    pdf.set_fill_color(*color_map.get(task_status, (128, 128, 128)))
    pdf.rect(bar_x, current_y, bar_width, bar_height, 'F')
    current_y += bar_height + 4

pdf.ln(10)
pdf.set_font("Arial", 'I', 10)
pdf.cell(0, 10, "* Task colors: Red - Pending, Orange - Ongoing, Green - Completed", ln=True)

# Define folder path for saving PDFs
pdf_folder = "C:/xampp/htdocs/projectify/reports/"
os.makedirs(pdf_folder, exist_ok=True)  # Ensure directory exists

pdf_filename = f"project_report_{project_id}.pdf"
pdf_path = os.path.join(pdf_folder, pdf_filename)
pdf.output(pdf_path)

print(f"PDF Generated Successfully: reports/{pdf_filename}")
