import sys
import PyPDF2
import google.generativeai as genai
import os

# Fetch the API key from an environment variable
api_key_genai = os.getenv('GENAI_API_KEY')

if not api_key_genai:
    print("API key is not set.")
    sys.exit(1)
    
# Configure the API key
genai.configure(api_key=api_key_genai)

def extract_pdf_text(pdf_path):
    # Open the PDF file
    with open(pdf_path, "rb") as file:
        reader = PyPDF2.PdfReader(file)
        text = ""
        for page in reader.pages:
            text += page.extract_text()
    return text

def generate_keywords(text):
    # Send the extracted text to Gemini API for keyword generation
    model = genai.GenerativeModel("gemini-1.5-flash")
    try:
        # Generate content with keywords only
        response = model.generate_content(f"Generate a list of keywords for the following text: {text}. Provide only keywords, separated by commas as output, nothing else. keywords to tag projects with them and use for fuzzy searching. Provide only keywords, separated by commas as output, nothing else.")
        
        # Clean up the output, remove any introductory or non-keyword content
        keywords = response.text.strip()
        
        # Remove introductory text (if present)
        if keywords.lower().startswith("here's a list of keywords"):
            keywords = keywords.split("\n\n")[1]  # Split and remove the intro section
        
        # Output the cleaned keywords
        print(keywords)
    except Exception as e:
        print(f"Error during keyword generation: {str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    pdf_path = sys.argv[1]  # The PDF file path passed from PHP
    text = extract_pdf_text(pdf_path)  # Extract text from the PDF
    generate_keywords(text)  # Generate keywords using Gemini API
