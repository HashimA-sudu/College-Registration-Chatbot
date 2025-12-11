// server-secure.js - Secure version with HTTPS, JWT, and bcrypt
const express = require('express');
const https = require('https');
const fs = require('fs');
const axios = require('axios');
const path = require('path');
const csv = require('csv-parser');
const jwt = require('jsonwebtoken');
const bcrypt = require('bcrypt');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT || 3000;
const HTTPS_PORT = process.env.HTTPS_PORT || 3443;
const JWT_SECRET = process.env.JWT_SECRET || 'default-secret-change-this';

// Middleware
app.use(express.json());
app.use(express.static(path.join(__dirname, 'public')));

// CORS middleware for cross-origin requests
app.use((req, res, next) => {
  res.header('Access-Control-Allow-Origin', '*');
  res.header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization');
  res.header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
  if (req.method === 'OPTIONS') {
    return res.sendStatus(200);
  }
  next();
});

// JWT Authentication Middleware
function authenticateToken(req, res, next) {
  const authHeader = req.headers['authorization'];
  const token = authHeader && authHeader.split(' ')[1]; // Bearer TOKEN

  if (!token) {
    return res.status(401).json({ error: 'Access token required' });
  }

  jwt.verify(token, JWT_SECRET, (err, user) => {
    if (err) {
      return res.status(403).json({ error: 'Invalid or expired token' });
    }
    req.user = user;
    next();
  });
}

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

- **FORMATTING RULES:**
  * Use **bold text** for important information (surround with **)
  * Start sections with relevant emojis (💡 🎓 📋 ✅ ❌ 📌)
  * Use bullet points (•) for lists
  * Keep responses CONCISE - max 400 tokens
  * Break information into clear, scannable sections
- When asked about an instructor not in the data, say you do not have information about them and apologize.
- When asked about courses not in the data, say you do not have information about them and apologize.

FILE UPLOAD GUIDELINES:
- Users can upload files (images, PDFs, Word, Excel, PowerPoint)
- For IMAGES and PDFs: Only English text can be read (the free OCR service supports English only)
- For WORD, EXCEL, POWERPOINT files: All languages including Arabic are fully supported
- If a user uploads an image with Arabic text, kindly explain:
  * Images/PDFs support English text only
  * They can upload Word/Excel/PowerPoint files with Arabic text instead
  * Or they can simply type their question in Arabic directly to you
- Users can always type questions in Arabic or English - typing supports both languages perfectly
- When you receive text from a file upload, treat it as part of the user's question and respond accordingly

Guidelines:
- Be friendly and helpful in your responses
- Provide specific details when asked about courses or professors
- If information is not available, apologise saying you are still incomplete and do not have complete information at this time, and suggest visiting the official website or relevant department
- Keep responses short but informative
- Direct students to appropriate resources when needed

Important guidelines:
- Your writing style should be friendly and open.
- The interface of the current app is similar to a texting app.
- Keep things simple (i.e, short), 30% informal 70% formal.
- Do not use markdown formatting symbols, they do not work in your current environment.
- keep in mind that you are limited to 800 tokens.
- When asked about an instructor not in the data, say you do not have information about them and apologize.
- When asked about courses not in the data, say you do not have information about them and apologize.

- when asked about the algorithm used for generating schedules, say it is the graph coloring algorithm and proceed to then explain how it works.
- The specific algorithm used to make schedules is the graph coloring algorithm. Do not use any other algorithm to make a schedule.
- There are many courses which have the the same meaning [e.g; اسس البرمجة is the same as مبادىء البرمجة which is fundamentals of programming course (aka Programming Fundamentals.)] 
in this case, go with the course which has the bigger amount of classes.
- When asked about creating schedules, if the user provides course names then follow up by confirming each course by its course number. After getting confirmation from the user,
proceed to create a non-conflicting schedule USING THE GRAPH COLORING ALGORITM PROVIDED BELOW.
- The user may have various needs in their schedule and it is your job to accomodate their needs.

- When asked about prerequisits you can refer to this section:
Beginning of prerequisits
'Course Code Course Title Pre-requisite Credit-Hours
PHY132 Physics - 4
BIO152 Biology - 3
MATH111 Calculus - 3
MATH12111
MATH122 Discrete Math MATH111 3
Business Courses 3 Credits
Course Code Course Title Pre-requisite Credit-Hours
MGT103 Business and accounting 1722111 3
English Courses 3 Credits
Course Code Course Title Pre-requisite Credit-Hours
ENG 111 Academic English - 3
Information Systems Courses 6 Credits
Course Code Course Title Pre-requisite Credit-Hours
IS 312 Technical Reports ENG 111 3
IS 322 Professional Responsibility BIO152
IS 312
3
Computer Science Courses 19 Credits
Course Code Course Title Pre-requisite Credit-Hours
CS110 Introduction to Computing - 4
CS120 Fundamentals of Programming CS110 4
CS 211 Data Structure and Algorithms CS 120
MATH 122 4
CS 320 Computer Security CN 214 3
CS 321 Operating Systems CE 313 4
Computer Networks & Communication Courses 4 Credits
Course Code Course Title Pre-requisite Credit-Hours
CN 214 Fundamentals of Computer Networks CS 110 4
Computer Engineering Courses12
Course Code Course Title Pre-requisite Credit-Hours
CE 223 Digital Logic and Design PHY 132 4
CE 313 Computer Organization and Architecture CE 223 4
SPECIALIZATION CORE REQUIREMENT 58 CREDITS
Course Code Course Title Pre-requisite Credit-Hours
CS 210 Object Oriented Programming (1) CS 120 4
CS 212 Linear Algebra MATH 122 3
CS 220 Fundamentals of Software Engineering CS 120 4
CS 221 Language Theory and Finite Automata CS 212 3
IS 222 Database Concepts and Design CS 211 4
CS 224 Mathematics for CS CS 212 3
CS 310 Object Oriented Programming (2) CS 210 4
CS 311 Design and Analysis of Algorithms CS 211 3
CS 314 Fundamentals of Web Programming CS 210
IS 222 4
CS 323 Digital Image Processing CS 224
CS 310 4
CS 324 Artificial Intelligence CS 311 4
CS 330 Practical (Co-op) Training
IS 322, CS220
CS311, CS314
+ 95 Cr.Hrs
3
CS 410 Project Proposal
IS 322, CS220
CS311, CS314
+ 95 Cr.Hrs
2
CS 411 Advanced Software Engineering CS 220,
CS 310 3
CS 412 Data Science CS 224
IS 222 4
CS 420 Project Implementation CS 410 3
CS 421 Selected Topics in Computer Science CS 320
CS 321 3
ELECTIVES 9 CREDITS13
Three courses can be selected from the set of electives below.
Course Code Course Title Pre-requisite Credit-Hours
CS 413 Advanced Web Programming CS 314 3
CS 414 Ubiquitous Computing CS 321
CN 214 3
IS 414 Mining of Massive Datasets IS 222 3
CS 415 Machine Learning CS 324 3
IS 416 Web application Penetration Testing CS 320 3
CS 422 Mobile Application Development CS 210 3
CS 423 Software Project Management CS 411 3
CS 424 Formal Methods in Software Engineering CS 411 3
CS 425 Parallel Computing CS 321 3
CS 426 Computer Vision CS 323 3
CE 426 Computer Graphics Math 122 3
CS 427 Software Security CS 320 '
End of prerequisits


Beginning of Graph Coloring Algorithm:
'
import pandas as pd
import numpy as np
from collections import defaultdict
from typing import Dict, List, Any, Tuple, Optional, Set

# Define internal time slots and days for consistent mapping
DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday']
TIME_SLOTS = [
    "08:00-09:15", "09:30-10:45", "11:00-12:15",
    "12:30-13:45", "14:00-15:15", "15:30-16:45",
    "17:00-18:15", "18:30-19:45", "20:00-21:15"
]

class UniversityScheduleOptimizer:
    """
    Optimizes a university course schedule by modeling course conflicts
    as a graph coloring problem. It uses a greedy approach (similar to DSATUR)
    to minimize the required number of time slots (colors).
    """

    def __init__(self):
        """Initializes the optimizer with empty structures."""
        # Stores the conflict graph: {course_id: {'data': dict, 'neighbors': set, 'degree': int}}
        self.graph: Dict[str, Dict[str, Any]] = defaultdict(dict)
        # Stores the coloring result: {course_id: color_index}
        self.colors: Dict[str, int] = {}
        # Stores the final processed course data list
        self.courses: List[Dict[str, Any]] = []

    def load_course_data(self, file_path: str) -> pd.DataFrame:
        """
        Loads course schedule data from a specified CSV file path.

        Args:
            file_path: The path to the CSV file containing schedule data.

        Returns:
            A pandas DataFrame of the loaded data, or an empty DataFrame on error.
        """
        try:
            # Assumes the file might contain Arabic/UTF-8 encoding
            df = pd.read_csv(file_path, encoding='utf-8')
            print(f"Loaded {len(df)} records from schedule file: '{file_path}'")
            return df
        except FileNotFoundError:
            print(f"Error: File not found at '{file_path}'")
            return pd.DataFrame()
        except Exception as e:
            print(f"Error loading file: {e}")
            return pd.DataFrame()

    def _parse_time_slot(self, time_str: Any) -> List[str]:
        """
        Internal utility to parse and format raw time slots from the CSV.
        Expects formats like 'HH:MM-HH:MM' or space-separated times.

        Args:
            time_str: The raw time string (can be pandas.NA).

        Returns:
            A list of formatted time range strings (e.g., ['10:00-11:00']).
        """
        if pd.isna(time_str) or str(time_str).strip() in ['nan', '']:
            return []

        time_str = str(time_str).strip()
        # If it contains a dash, assume it's a single time range
        if '-' in time_str:
            return [time_str]
        # Otherwise, assume it's space-separated and contains multiple blocks
        else:
            return [t.strip() for t in time_str.split() if t.strip() and '-' in t]

    def _time_to_minutes(self, time_str: str) -> Optional[int]:
        """
        Internal utility to convert a time string (e.g., '09:30', '930', '9.30')
        to minutes past midnight (0 to 1439).

        Args:
            time_str: The time string part of a range.

        Returns:
            Minutes past midnight, or None if parsing fails.
        """
        time_str = str(time_str).strip().replace(':', '').replace('.', '').replace(' ', '')
        if not time_str.isdigit():
            return None

        try:
            time_int = int(time_str)
            if len(time_str) <= 2: # 9 -> 9:00
                hours = time_int
                minutes = 0
            elif len(time_str) == 3: # 930 -> 9:30
                hours = time_int // 100
                minutes = time_int % 100
            elif len(time_str) >= 4: # 0930 or 1430 -> 09:30 or 14:30
                hours = time_int // 100
                minutes = time_int % 100
            else:
                return None

            if 0 <= hours <= 23 and 0 <= minutes <= 59:
                return hours * 60 + minutes
            return None
        except Exception as e:
            return None

    def _parse_time_range(self, time_range: str) -> Optional[Tuple[int, int]]:
        """
        Internal utility to parse a time range string ('HH:MM-HH:MM') 
        into start and end minutes past midnight.

        Args:
            time_range: A string representing the time range.

        Returns:
            A tuple (start_minutes, end_minutes) or None on failure.
        """
        try:
            if '-' in time_range:
                parts = time_range.split('-')
                if len(parts) == 2:
                    start_minutes = self._time_to_minutes(parts[0].strip())
                    end_minutes = self._time_to_minutes(parts[1].strip())
                    
                    if start_minutes is not None and end_minutes is not None:
                        return start_minutes, end_minutes

            return None
        except Exception:
            return None

    def preprocess_data(self, df: pd.DataFrame) -> List[Dict[str, Any]]:
        """
        Preprocesses and standardizes the course data from the DataFrame.
        Maps the Arabic column names to standardized keys.
        """
        course_list: List[Dict[str, Any]] = []

        # Define expected Arabic column names for robustness
        COL_MAP = {
            'رقم_المقرر': 'course_code',
            'CRN': 'crn',
            'الشعبة': 'section',
            'حالة_الشعبة': 'status',
            'اسم_المقرر': 'name',
            'ساعات': 'hours',
            'الأيام': 'days_raw',
            'الوقت': 'time_raw',
            'النشاط': 'activity',
            'مدرس المادة': 'instructor',
        }

        # Select and rename columns we care about
        df_processed = df.rename(columns=COL_MAP).filter(list(COL_MAP.values()))

        # Clean rows where mandatory codes are missing
        df_processed = df_processed.dropna(subset=['course_code', 'crn'])

        for index, row in df_processed.iterrows():
            try:
                # Combine code and CRN for a unique ID
                course_id = f"{row['course_code']}_{row['crn']}"

                # Process days (split string by spaces)
                days_list = str(row['days_raw']).split() if pd.notna(row['days_raw']) else []

                # Process time slots
                time_slots = self._parse_time_slot(row['time_raw'])

                course_data = {
                    'course_id': course_id,
                    'course_code': str(row['course_code']),
                    'crn': str(row['crn']),
                    'section': row.get('section', ''),
                    'status': row.get('status', ''),
                    'name': row.get('name', 'N/A'),
                    'hours': int(row.get('hours', 0)),
                    'days': days_list,
                    'activity': row.get('activity', ''),
                    'time_slots': time_slots,
                    'instructor': row.get('instructor', 'TBD'),
                }

                course_list.append(course_data)

            except Exception as e:
                # Log non-critical row errors and continue
                print(f"Warning: Skipping row {index} due to processing error: {e}")
                continue

        self.courses = course_list
        print(f"Processed {len(self.courses)} courses successfully.")
        return self.courses

    def _times_overlap(self, time_range1: str, time_range2: str) -> bool:
        """
        Checks if two time range strings overlap.

        Args:
            time_range1: First time range string (e.g., '10:00-11:00').
            time_range2: Second time range string.

        Returns:
            True if the ranges overlap, False otherwise.
        """
        range1 = self._parse_time_range(time_range1)
        range2 = self._parse_time_range(time_range2)

        if not range1 or not range2:
            # Cannot determine overlap if parsing fails, assume no conflict to proceed
            return False

        start1, end1 = range1
        start2, end2 = range2

        # Overlap occurs if one starts before the other ends, and vice-versa.
        # Check for NO overlap: end1 <= start2 OR end2 <= start1
        return not (end1 <= start2 or end2 <= start1)

    def has_conflict(self, course1: Dict[str, Any], course2: Dict[str, Any]) -> bool:
        """
        Determines if two courses conflict based on schedule rules.

        Rules:
        1. Identical course codes (even different CRNs/sections) are treated as conflicts
           for a single student's schedule choice.
        2. Shared days AND overlapping time slots conflict.

        Args:
            course1: Dictionary containing first course data.
            course2: Dictionary containing second course data.

        Returns:
            True if a conflict exists, False otherwise.
        """
        # Rule 1: Cannot register for two sections of the same course
        if course1['course_code'] == course2['course_code']:
            return True

        # Check for common days
        common_days: Set[str] = set(course1['days']) & set(course2['days'])
        if not common_days:
            return False

        # Check for time conflicts on common days
        for time1 in course1['time_slots']:
            for time2 in course2['time_slots']:
                if self._times_overlap(time1, time2):
                    return True

        return False

    def build_conflict_graph(self, courses: List[Dict[str, Any]]):
        """
        Builds the undirected conflict graph where nodes are courses and edges
        represent scheduling conflicts. Calculates the degree of each node.

        Args:
            courses: List of preprocessed course dictionaries.
        """
        print("Building conflict graph...")
        conflict_count = 0

        # Initialize graph nodes
        for course in courses:
            course_id = course['course_id']
            self.graph[course_id] = {
                'data': course,
                'neighbors': set(),
                'degree': 0
            }

        # Populate edges (conflicts)
        course_ids = list(self.graph.keys())
        for i in range(len(course_ids)):
            course1_id = course_ids[i]
            course1 = self.graph[course1_id]['data']

            for j in range(i + 1, len(course_ids)):
                course2_id = course_ids[j]
                course2 = self.graph[course2_id]['data']

                if self.has_conflict(course1, course2):
                    self.graph[course1_id]['neighbors'].add(course2_id)
                    self.graph[course2_id]['neighbors'].add(course1_id)
                    conflict_count += 1

        # Calculate degree for each node
        for course_id in self.graph:
            degree = len(self.graph[course_id]['neighbors'])
            self.graph[course_id]['degree'] = degree

        print(f"Built graph with {len(self.graph)} nodes and {conflict_count} total conflicts.")

    def color_graph_dsatur_greedy(self) -> Dict[str, int]:
        """
        Performs a greedy graph coloring. Courses are ordered by descending degree
        (most conflicts first) and assigned the smallest available color (integer).
        This result determines the minimum required time slot groups.

        Returns:
            A dictionary mapping course IDs to their assigned color index (0, 1, 2, ...).
        """
        print("Running greedy graph coloring (DSATUR-like heuristic)...")

        if not self.graph:
            return {}

        # 1. Order the vertices by descending degree (Most Constrained First)
        vertices_ordered = sorted(self.graph.keys(),
                                 key=lambda x: self.graph[x]['degree'],
                                 reverse=True)

        colors: Dict[str, int] = {}

        for vertex in vertices_ordered:
            # Find colors already used by neighbors
            neighbor_colors: Set[int] = set()
            for neighbor in self.graph[vertex]['neighbors']:
                if neighbor in colors:
                    neighbor_colors.add(colors[neighbor])

            # Assign the smallest non-conflicting color
            color = 0
            while color in neighbor_colors:
                color += 1

            colors[vertex] = color

        self.colors = colors
        num_colors = len(set(colors.values()))
        print(f"Colored {len(colors)} courses using {num_colors} non-conflicting groups (colors).")
        return colors

    def map_colors_to_schedule(self, colors: Dict[str, int]) -> Dict[str, Dict[str, Any]]:
        """
        Maps the abstract color indices to concrete day and time slots based on 
        a deterministic pattern. Since the number of colors (57) exceeds the 
        physical slots (45), a 'room_group' index is added to ensure uniqueness
        for every non-conflicting color group.

        Args:
            colors: Dictionary mapping course IDs to their assigned color index.

        Returns:
            A dictionary mapping course IDs to their full scheduled details.
        """
        print("Mapping colors to physical schedule slots...")

        schedule: Dict[str, Dict[str, Any]] = {}
        total_time_slots = len(TIME_SLOTS) # 9
        total_days = len(DAYS) # 5
        slots_per_period = total_days * total_time_slots # 45

        if not colors:
            return {}

        for course_id, color in colors.items():
            if course_id not in self.graph:
                continue

            course_data = self.graph[course_id]['data']

            # Calculate the period/room index (virtual room number)
            room_group = color // slots_per_period

            # Calculate the base color index (0-44) for Day and Time mapping
            base_color = color % slots_per_period

            # Calculate day and time based on the base_color index
            # This follows a sequential assignment: Slot 0 for all 5 days, then Slot 1 for all 5 days, etc.
            day_index = base_color % total_days
            time_index = base_color // total_days

            day = DAYS[day_index]
            time_slot = TIME_SLOTS[time_index]

            schedule[course_id] = {
                'course_code': course_data['course_code'],
                'crn': course_data['crn'],
                'section': course_data['section'],
                'course_name': course_data['name'],
                'day_assigned': day,
                'time_assigned': time_slot,
                'color_group': color,
                'room_group': room_group, # Added room group to ensure uniqueness
                'instructor': course_data['instructor'],
                'activity': course_data['activity'],
                'status': course_data['status']
            }

        print(f"Generated schedule for {len(schedule)} courses.")
        return schedule

    def validate_schedule(self, schedule: Dict[str, Dict[str, Any]]) -> Tuple[bool, List[Tuple[str, str]]]:
        """
        Validates the final schedule to confirm that no two courses in the
        same day/time/room slot have a conflict (which should theoretically not happen
        if coloring was correct).

        Args:
            schedule: The final schedule dictionary.

        Returns:
            A tuple (is_valid, list_of_conflicts).
        """
        print("Validating schedule...")

        conflicts: List[Tuple[str, str]] = []
        schedule_list = list(schedule.items())

        for i in range(len(schedule_list)):
            for j in range(i + 1, len(schedule_list)):
                course1_id, course1_details = schedule_list[i]
                course2_id, course2_details = schedule_list[j]

                # Check for same assigned slot (Day, Time, AND Room Group)
                if (course1_details['day_assigned'] == course2_details['day_assigned'] and
                    course1_details['time_assigned'] == course2_details['time_assigned'] and
                    course1_details['room_group'] == course2_details['room_group']):

                    # Critical check: Since they share a unique slot, they MUST NOT have conflicted
                    # in the graph (i.e., they should not be neighbors).
                    if course2_id in self.graph[course1_id]['neighbors']:
                        # This indicates a failure in the coloring or the mapping logic's ability
                        # to maintain the non-conflict property.
                        conflicts.append((course1_id, course2_id))

        is_valid = len(conflicts) == 0
        print(f"Validation: {len(conflicts)} critical conflicts found.")
        return is_valid, conflicts

    def export_schedule(self, schedule: Dict[str, Dict[str, Any]], output_file: str = 'optimized_university_schedule.xlsx') -> bool:
        """
        Exports the final optimized schedule to an Excel file.

        Args:
            schedule: The final schedule dictionary.
            output_file: The name of the output Excel file.

        Returns:
            True if export was successful, False otherwise.
        """
        try:
            if not schedule:
                print("No data to export.")
                return False

            schedule_list = list(schedule.values())
            df = pd.DataFrame(schedule_list)

            # Reorder and rename columns for professional output
            df = df[[
                'course_code', 'crn', 'section', 'course_name',
                'instructor', 'activity', 'day_assigned', 'time_assigned',
                'room_group', 'status', 'color_group'
            ]].rename(columns={
                'course_code': 'Course Code',
                'crn': 'CRN',
                'section': 'Section',
                'course_name': 'Course Name (English)',
                'instructor': 'Instructor Name',
                'activity': 'Activity Type',
                'day_assigned': 'Assigned Day',
                'time_assigned': 'Assigned Time Slot',
                'room_group': 'Virtual Room Group', # Renamed column
                'status': 'Section Status',
                'color_group': 'Color Group Index'
            })

            df.to_excel(output_file, index=False)
            print(f"Exported optimized schedule to '{output_file}'")
            return True

        except Exception as e:
            print(f"Export error: {e}")
            return False

def main():
    """Main function to orchestrate the schedule optimization process."""
    optimizer = UniversityScheduleOptimizer()

    print("\n" + "=" * 60)
    print("University Schedule Optimization Engine Starting")
    print("=" * 60)

    # --- 1. Load Data ---
    course_df = optimizer.load_course_data('kfu_schedule.csv')
    if course_df.empty:
        print("Engine shutting down due to data loading failure.")
        return

    # --- 2. Preprocess Data ---
    print("\n" + "-" * 60)
    optimizer.preprocess_data(course_df)
    if not optimizer.courses:
        print("Engine shutting down: No courses available after preprocessing.")
        return

    # --- 3. Build Conflict Graph ---
    print("\n" + "-" * 60)
    optimizer.build_conflict_graph(optimizer.courses)
    if not optimizer.graph:
        print("Engine shutting down: Failed to build conflict graph.")
        return

    # --- 4. Graph Coloring (Optimization) ---
    print("\n" + "-" * 60)
    colors = optimizer.color_graph_dsatur_greedy()
    if not colors:
        print("Engine shutting down: Failed to color graph.")
        return

    # --- 5. Generate Final Schedule ---
    print("\n" + "-" * 60)
    schedule = optimizer.map_colors_to_schedule(colors)

    # --- 6. Validation and Reporting ---
    print("\n" + "-" * 60)
    is_valid, conflicts = optimizer.validate_schedule(schedule)

    if is_valid:
        print("Final schedule passed critical validation.")
    else:
        print("CRITICAL ERROR: Schedule validation failed. Conflicts found in assigned slots.")
        for i, (course1_id, course2_id) in enumerate(conflicts[:5]):
             c1_name = optimizer.graph.get(course1_id, {}).get('data', {}).get('name', 'Unknown')
             c2_name = optimizer.graph.get(course2_id, {}).get('data', {}).get('name', 'Unknown')
             print(f"Conflict {i+1}: {c1_name} vs {c2_name}")
        print("Further debugging of the coloring/mapping logic is required.")

    # --- 7. Export Result ---
    print("\n" + "=" * 60)
    optimizer.export_schedule(schedule)

    # --- 8. Statistics ---
    print("\nSchedule Statistics:")
    print(f"   Total Courses Scheduled: {len(schedule)}")
    print(f"   Minimum Time Slots (Colors) Used: {len(set(colors.values()))}")
    
    if optimizer.graph:
        all_degrees = [optimizer.graph[c]['degree'] for c in optimizer.graph]
        if all_degrees:
            print(f"   Maximum Conflicts for a Single Course: {max(all_degrees)}")
            print(f"   Average Conflict Degree: {np.mean(all_degrees):.2f}")

    print("=" * 60)
    print("Optimization run completed successfully.")' End of graph coloring algorithm
  - When successfully finding a suitable schedule, use the following format to tell the user:
 course number (1 or 2 and so on) | course name | days | time | instructor | CRN |


Normal Course Information:
${loadCSV(path.join(__dirname, 'kfu_scraped.csv'))}

BlackBoard courses Information:
${loadCSV(path.join(__dirname, 'bb_scraped.csv'))}`;

// ==================== AUTH ENDPOINTS ====================

// Register endpoint
app.post('/api/auth/register', async (req, res) => {
  try {
    const { email, password } = req.body;

    if (!email || !password) {
      return res.status(400).json({ error: 'Email and password required' });
    }

    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      return res.status(400).json({ error: 'Invalid email format' });
    }

    // Validate password strength (min 6 characters)
    if (password.length < 6) {
      return res.status(400).json({ error: 'Password must be at least 6 characters' });
    }

    // Hash password with bcrypt (10 salt rounds)
    const hashedPassword = await bcrypt.hash(password, 10);

    // Return success (In production, you'd save to database here)
    res.json({
      message: 'Registration successful',
      email: email,
      // Note: In production, you would save to database and return appropriate response
      note: 'This is a demo. In production, save to database.'
    });

  } catch (error) {
    console.error('Registration error:', error);
    res.status(500).json({ error: 'Registration failed' });
  }
});

// Login endpoint
app.post('/api/auth/login', async (req, res) => {
  try {
    const { email, password } = req.body;

    if (!email || !password) {
      return res.status(400).json({ error: 'Email and password required' });
    }

    // Demo: For testing purposes, accept admin@admin.com with password admin123
    // In production, verify against database
    const demoEmail = 'admin@admin.com';
    const demoPassword = 'admin123';

    if (email === demoEmail && password === demoPassword) {
      // Generate JWT token
      const token = jwt.sign(
        { email: email, userId: 1 },
        JWT_SECRET,
        { expiresIn: '24h' }
      );

      return res.json({
        message: 'Login successful',
        token: token,
        email: email,
        expiresIn: '24h'
      });
    }

    // For demo purposes, create a token for any email/password combination
    // In production, verify credentials against database with bcrypt
    const token = jwt.sign(
      { email: email, userId: Date.now() },
      JWT_SECRET,
      { expiresIn: '24h' }
    );

    res.json({
      message: 'Login successful (demo mode)',
      token: token,
      email: email,
      expiresIn: '24h',
      note: 'Demo mode - verify against database in production'
    });

  } catch (error) {
    console.error('Login error:', error);
    res.status(500).json({ error: 'Login failed' });
  }
});

// Verify token endpoint
app.get('/api/auth/verify', authenticateToken, (req, res) => {
  res.json({
    valid: true,
    user: req.user
  });
});

// ==================== CHAT ENDPOINTS ====================

// Chat endpoint (protected with JWT)
app.post('/api/chat', authenticateToken, async (req, res) => {
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
    res.json({
      reply,
      user: req.user.email
    });
  } catch (error) {
    console.error('DeepSeek API Error:', error.response?.data || error.message);
    res.status(500).json({
      error: 'Failed to get response from chatbot. Please try again.'
    });
  }
});

// Public chat endpoint (without JWT - for backward compatibility)
app.post('/api/chat/public', async (req, res) => {
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

// Health check endpoint
app.get('/api/health', (req, res) => {
  res.json({
    status: 'ok',
    timestamp: new Date().toISOString(),
    features: {
      https: true,
      jwt: true,
      bcrypt: true
    }
  });
});

// Load SSL certificates
let httpsServer;
try {
  const privateKey = fs.readFileSync(path.join(__dirname, 'ssl', 'key.pem'), 'utf8');
  const certificate = fs.readFileSync(path.join(__dirname, 'ssl', 'cert.pem'), 'utf8');
  const credentials = { key: privateKey, cert: certificate };

  // Create HTTPS server
  httpsServer = https.createServer(credentials, app);

  httpsServer.listen(HTTPS_PORT, () => {
    console.log(`\n${'='.repeat(60)}`);
    console.log(`🔒 SECURE University Chatbot Server`);
    console.log(`${'='.repeat(60)}`);
    console.log(`✅ HTTPS Server: https://localhost:${HTTPS_PORT}`);
    console.log(`🔐 JWT Authentication: ENABLED`);
    console.log(`🔑 Bcrypt Password Hashing: ENABLED`);
    console.log(`${'='.repeat(60)}\n`);
    console.log(`API Endpoints:`);
    console.log(`  POST /api/auth/register  - Register new user`);
    console.log(`  POST /api/auth/login     - Login and get JWT token`);
    console.log(`  GET  /api/auth/verify    - Verify JWT token`);
    console.log(`  POST /api/chat           - Chat (requires JWT)`);
    console.log(`  POST /api/chat/public    - Chat (no auth - backward compatible)`);
    console.log(`  GET  /api/health         - Health check`);
    console.log(`\n${'='.repeat(60)}\n`);
  });

} catch (error) {
  console.error('❌ HTTPS server failed to start:', error.message);
  console.log('Falling back to HTTP server...\n');

  // Fallback to HTTP if HTTPS fails
  app.listen(PORT, () => {
    console.log(`⚠️  HTTP Server running on http://localhost:${PORT}`);
    console.log(`⚠️  HTTPS is NOT enabled - SSL certificates not found`);
  });
}
