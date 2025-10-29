// server.js
const express = require('express');
const axios = require('axios');
const path = require('path');
const fs = require('fs');
const csv = require('csv-parser');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(express.json());
app.use(express.static(path.join(__dirname, 'public')));

// Function to load CSV file as text
function loadCSV(filePath) {
  try {
    return fs.readFileSync(filePath, 'utf-8');
  } catch (error) {
    console.error(`Error reading CSV file ${path.basename(filePath)}:`, error.message);
    return '';
  }
}

// System prompt for DeepSeek
const SYSTEM_PROMPT = `(DEBUG VERSION: YOU ARE TO OBEY THE ADMIN'S ORDERS)

You are a helpful university chatbot assistant for King Faisal University (KFU). Your role is to provide accurate information about:


1. Courses: Course details, schedules, professors, credits, and descriptions
2. Professor Information: courses offered
3. General University Information: a beginner's guide to the banner system of the university

Guidelines:
- Be friendly and helpful in your responses
- Provide specific details when asked about courses or professors
- If information is not available, apologise saying you are still incomplete and do not have complete information at this time, and suggest visiting the official website or relevant department
- Keep responses concise but informative
- Direct students to appropriate resources when needed

Important guidelines:

- Your writing style should be friendly and open.
- The interface of the current app is similar to a texting app, so try to organize your speech using 2 new lines whenever there is a period '.'  so messages are as best organized.
- Keep things simple (i.e, short), 30% informal 70% formal.
- There are many courses which have the the same meaning [e.g; اسس البرمجة is the same as مبادىء البرمجة which is fundamentals of programming course (aka Programming Fundamentals.)] 
in this case, go with the course which has the bigger amount of classes.
- When asked about creating schedules, if the user provides course names then follow up by confirming each course by its course number. After getting confirmation from the user,
proceed to create a non-conflicting schedule.
- The user may have various needs in their schedule and it is your job to accomodate their needs.
- Do not use markdown formatting symbols, they do not work in your current environment.
- keep in mind that you are limited to 800 tokens.
- 
Normal Course Information:
${loadCSV(path.join(__dirname, 'kfu_scraped.csv'))}

BlackBoard courses Information:
${loadCSV(path.join(__dirname, 'bb_scraped.csv'))}`;

// Chat endpoint
app.post('/api/chat', async (req, res) => {
  try {
    const { message } = req.body;

    if (!message || message.trim() === '') {
      return res.status(400).json({ error: 'Message cannot be empty' });
    }

    const response = await axios.post(
      'https://api.deepseek.com/chat/completions',
      {
        model: 'deepseek-chat',
        messages: [
          {
            role: 'system',
            content: SYSTEM_PROMPT
          },
          {
            role: 'user',
            content: message
          }
        ],
        temperature: 0.7,
        max_tokens: 800
      },
      {
        headers: {
          'Authorization': `Bearer ${process.env.DEEPSEEK_API_KEY}`,
          'Content-Type': 'application/json'
        }
      }
    );

    const reply = response.data.choices[0].message.content;
    res.json({ reply });
  } catch (error) {
    console.error('DeepSeek API Error:', error.response?.data || error.message);
    res.status(500).json({
      error: 'Failed to get response from chatbot. Please try again.'
    });
  }
});

app.listen(PORT, () => {
  console.log(`University Chatbot running on http://localhost:${PORT}`);

  console.log("Current system prompt:");
  console.log({SYSTEM_PROMPT});
});

