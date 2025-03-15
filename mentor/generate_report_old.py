import pymysql
import sys
from fpdf import FPDF
import os

with open("log.txt","w") as f:
    f.write("here")

if len(sys.argv) < 2:
    print("Error: Project ID not provided!")
    sys.exit(1)

project_id = sys.argv[1]  

conn = pymysql.connect(host="localhost", user="root", password="", database="projectify", port=3307)
cursor = conn.cursor()

cursor.execute("""
    SELECT id, name, description, status, keywords, year_and_batch, git_repo_link, frozen, interested_domains, pdf_path
    FROM project 
    WHERE id = %s
""", (project_id,))

project = cursor.fetchone()

if not project:
    print(f"❌ Error: No project found for project_id = {project_id}")
    sys.exit(1)

project_id, name, description, status, keywords, year_and_batch, git_repo_link, frozen, interested_domains, pdf_path = project

pdf = FPDF()
pdf.set_auto_page_break(auto=True, margin=15)
pdf.add_page()
pdf.set_font("Arial", size=12)

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

# Define the folder path where PDFs should be saved
pdf_folder = "C:/xampp/htdocs/projectify/reports/"
if not os.path.exists(pdf_folder):
    os.makedirs(pdf_folder)  # Create the folder if it doesn't exist

pdf_filename = f"project_report_{project_id}.pdf"
pdf_path = os.path.join(pdf_folder, pdf_filename)
pdf.output(pdf_path)

print(f"PDF Generated Successfully: reports/{pdf_filename}")