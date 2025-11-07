<!doctype html>
<html lang="en" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin — Dashboard</title>
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
  <main class="container">
    <header class="row between">
      <h1>Dashboard</h1>
      <div class="row gap">
        <a class="btn ghost" href="courses.html">Courses</a>
        <a class="btn ghost" href="sections.html">Sections</a>
        <button id="logout" class="btn">Logout</button>
      </div>
    </header>

    <section class="grid-3">
      <div class="card kpi"><div class="kpi-num" id="kCourses">–</div><div class="kpi-label">Courses</div></div>
      <div class="card kpi"><div class="kpi-num" id="kSections">–</div><div class="kpi-label">Sections</div></div>
      <div class="card kpi"><div class="kpi-num" id="kUpdated">–</div><div class="kpi-label">Last Update</div></div>
    </section>

    <section class="card">
      <h2 class="card-title">Recent activity</h2>
      <pre id="activity" class="scroll"></pre>
    </section>
  </main>

  <script>
    const API = localStorage.getItem("API_BASE") || "http://localhost:5000";

    async function fetchJSON(url, opt={}){
      const res = await fetch(url, { credentials:"include", ...opt });
      if(!res.ok) throw new Error("HTTP " + res.status);
      return res.json();
    }

    async function load(){
      try{
        const cs = await fetchJSON(API + "/api/admin/courses");
        const ss = await fetchJSON(API + "/api/admin/sections");
        document.getElementById("kCourses").textContent = cs?.length ?? 0;
        document.getElementById("kSections").textContent = ss?.length ?? 0;
        document.getElementById("kUpdated").textContent = new Date().toLocaleString();
        document.getElementById("activity").textContent =
          "- Loaded " + (cs?.length??0) + " courses\n- Loaded " + (ss?.length??0) + " sections";
      }catch(_){
        document.getElementById("activity").textContent = "Failed to load. Check login or server.";
      }
    }

    document.getElementById("logout").onclick = async ()=>{
      try{ await fetch(API + "/api/auth/logout", {method:"POST", credentials:"include"}); }catch(_){}
      location.href = "login.html";
    };

    load();
  </script>
</body>
</html>
