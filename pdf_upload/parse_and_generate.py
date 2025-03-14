import os
import sys
import json
from PyPDF2 import PdfReader
import google.generativeai as genai



# Configure Gemini API key
# genai.configure(api_key= "AIzaSyAsFxypSQCymhBLbJHWAfkHZN6tOOI1nek" )
genai.configure(api_key=os.getenv("GEMINI_API_KEY"))

# Function to extract text from PDF
def extract_pdf_text(pdf_path):
    try:
        reader = PdfReader(pdf_path)
        text = ""
        for page in reader.pages:
            text += page.extract_text()
        return text
    except Exception as e:
        return f"Error reading PDF: {str(e)}"

# Function to generate keywords using Gemini
def generate_keywords(text):
    try:
        model = genai.GenerativeModel("gemini-1.5-flash")
        prompt = f"Extract 15 tech-related keywords from the following text:\n{text}. give me only 15 keywords separated by comma, nothing else. please"
        response = model.generate_content(prompt)
        return response.text if response and response.text else 'Error in keyword generation'
    except Exception as e:
        return [f"Error generating keywords: {str(e)}"]

# Main function to process the PDF
def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No PDF file path provided"}))
        return

    pdf_path = sys.argv[1]
    text = extract_pdf_text(pdf_path)

    if text.startswith("Error"):
        print(json.dumps({"error": text}))
        return

    keywords = generate_keywords(text)
    # Output only JSON formatted result
    # print(json.dumps({"keywords": keywords}))
    print(keywords)
    # with open ('a.txt', 'w') as f:
    #     f.write(str(sys.argv) + str(keywords))

if __name__ == "__main__":
    main()