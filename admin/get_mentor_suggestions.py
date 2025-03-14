# import sys
# import json
# import google.generativeai as genai

# # Configure Gemini API
# genai.configure(api_key="AIzaSyAsFxypSQCymhBLbJHWAfkHZN6tOOI1nek")
# model = genai.GenerativeModel("gemini-1.5-flash")

# # Receive data from PHP
# projects = sys.argv[1]
# # projects = "[{ id : project_674b06bde24677.65980048 , name : AI CHAT , description : AI chatbot for kids with down syndrome , keywords : ai, ml, php },{ id : proj_6740934b94c3f3.77197632 , name : Android APP , description : tree plantation app , keywords : MongoDB, database, collection }]"
# mentors = sys.argv[2]
# # mentors = "[{ id : a1 , name : Sufiyan , expertise : DART, flutter },{ id : m1 , name : Samarth , expertise : AIML, python }]"

# # projects = json.loads(projects_json)
# # mentors = json.loads(mentors_json)

# suggested_mentors = []

# prompt = f"Given the projects: {projects}, suggest a mentor from this list for each: {mentors}. Only return the names of the mentors separated by ;"
# response = model.generate_content(prompt)

# if not (response and response.text):
#     print('Error in keyword generation')
#     exit()
# suggested_mentors = response.text.split(';')

# Output results to PHP
for mentor_name in ['Samarth Shinde' , 'Sufiyan', 'XYZ']:
    print(mentor_name)