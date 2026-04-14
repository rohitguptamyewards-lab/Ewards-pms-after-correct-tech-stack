# eWards PMS Non-Technical Flowcharts

These flowcharts are based on the current Laravel + Vue/Inertia codebase in this repository.

The system is too broad to explain clearly in one readable picture, so this document gives:

1. A master business flow of the whole product
2. A detailed project lifecycle flow
3. A detailed work-log flow
4. A detailed alerts / notifications / automation flow
5. A detailed reporting flow
6. A simple record map showing what the system stores

## 1. Master Business Flow

```mermaid
flowchart TD
    A["Person opens the eWards PMS website"] --> B["Login page asks for email and password"]
    B --> C{"Are the login details correct?"}
    C -- "No" --> D["Show login error and ask the person to try again"]
    C -- "Yes" --> E["System opens the main dashboard"]
    E --> F["System checks the person's role<br/>Manager / Analyst Head / Analyst / Senior Developer / Developer / Employee"]
    F --> G["Show the menu and dashboard that match that role"]

    G --> H{"What does the person want to do next?"}

    H --> I["Create or manage projects"]
    H --> J["Log daily work"]
    H --> K["View dashboards and reports"]
    H --> L["Manage team members"]
    H --> M["Write release notes"]
    H --> N["Manage automations"]
    H --> O["Read notifications"]

    I --> P["Project area stores project details, tasks, people, stage history, comments, blockers, tickets, files, transfers, and release notes"]
    J --> Q["Work-log area stores time spent, work notes, status, blockers, and links the work to a project"]
    K --> R["Dashboard and reports read projects, planner tasks, blockers, stages, and work logs to show progress"]
    L --> S["Team-member area creates people, roles, and active/inactive status"]
    M --> T["Release-note area stores release summaries, attached files, and helpful links"]
    N --> U["Automation area stores rules that can send emails or in-app notifications"]
    O --> V["Notification center shows alerts and deep-links people back to the related project"]

    P --> W["Shared database keeps the business records"]
    Q --> W
    R --> W
    S --> W
    T --> W
    U --> W
    V --> W

    P --> X["System may also send emails when people are assigned or when stages change"]
    U --> X

    P --> Y["System may create in-app notifications"]
    U --> Y
    Y --> O
```

## 2. Detailed Project Lifecycle Flow

```mermaid
flowchart TD
    A["Lead user opens Project Create page<br/>(Manager / Analyst Head / Analyst)"] --> B["Person enters project basics<br/>name, owner, description, dates, priority, work type, task type"]
    B --> C["Person may also set analysts, developer, tester, linked projects, parent project, document links, and AI/chat links"]
    C --> D{"Is this a valid project?"}
    D -- "No" --> E["Show validation errors<br/>for example missing name, missing owner, bad dates, or too much subtask depth"]
    D -- "Yes" --> F["Save the project"]

    F --> G["System automatically adds the owner into the project worker list"]
    G --> H["System sends assignment emails to newly assigned analyst, tester, and developer"]
    H --> I["Project now appears in project lists, board view, dashboards, and reports"]

    I --> J["Team opens the project workspace"]
    J --> K{"What is updated inside the project?"}

    K --> L["Details tab<br/>change name, assignments, dates, links, priority, status, and other project info"]
    K --> M["Planner tab<br/>create ordered tasks / milestones and assign them to people"]
    K --> N["Workers tab<br/>add contributors, change owner, or transfer ownership"]
    K --> O["Attachments tab<br/>upload supporting files"]
    K --> P["Tickets tab<br/>store related ticket IDs or external references"]
    K --> Q["Blockers tab<br/>report something that is stopping progress"]
    K --> R["Release Notes tab<br/>record what changed for this project"]
    K --> S["Comments / Updates stream<br/>post manual updates and keep a timeline"]
    K --> T["Stage control<br/>move the project through documentation, development, testing, review, live testing, and live"]

    M --> U["Planner tasks are saved and shown in order"]
    N --> V["Worker list and ownership history are updated"]
    O --> W["Files are stored and linked back to the project"]
    P --> X["Ticket references are saved"]
    Q --> Y["Blocker is saved as active until someone resolves it"]
    R --> Z["Release note text, files, and links are saved"]
    S --> AA["Project timeline gets a new comment/update"]

    T --> AB["New stage record is added to stage history instead of overwriting the past"]
    AB --> AC["System also writes an automatic update saying who changed the stage"]
    AC --> AD["Relevant people receive email alerts for testing, development, or live stages"]
    AD --> AE["Relevant people also receive in-app notifications"]
    AE --> AF["Automation rules are checked for this stage change"]

    Y --> AG["Project owner and analyst can be notified about the blocker"]
    V --> AH["Ownership transfers are logged for later review"]

    U --> AI["Project progress becomes visible in dashboards and reports"]
    V --> AI
    W --> AI
    X --> AI
    Y --> AI
    Z --> AI
    AA --> AI
    AF --> AI

    AI --> AJ{"Is the project finished?"}
    AJ -- "No" --> J
    AJ -- "Yes" --> AK["Project reaches live / completed state and remains available for tracking, reporting, and history"]
```

## 3. Detailed Work-Log Flow

```mermaid
flowchart TD
    A["Team member opens Log Work page"] --> B["System shows project list and suggests today's last end time as the next start time"]
    B --> C{"What kind of work is being logged?"}

    C -- "Existing project" --> D["Person selects an existing project"]
    C -- "Custom work not yet in the system" --> E["Person chooses Add custom work and types a work name"]

    E --> F{"Does a project with that custom name already exist?"}
    F -- "Yes" --> G["Use the existing custom-work project"]
    F -- "No" --> H["System automatically creates a hidden custom-work project and makes the logger its owner"]
    H --> G

    D --> I["Person enters date, start time, end time, status, work description, and optional blocker"]
    G --> I

    I --> J{"Is the entry valid?"}
    J -- "No" --> K["Show errors<br/>for example no project, no description, or end time before start time"]
    J -- "Yes" --> L["System calculates hours spent from start and end time"]

    L --> M{"Is this linked to a normal project?"}
    M -- "Yes" --> N["System also stores a snapshot of the current project stage for historical reporting"]
    M -- "No, this is custom work" --> O["No project-stage snapshot is stored because the work item is only for time tracking"]

    N --> P["Save the work log"]
    O --> P

    P --> Q["Work-log list updates for the user"]
    Q --> R["Weekly total hours update"]
    R --> S["Manager dashboards, employee dashboards, team activity, workers report, and project reports can now use this time data"]

    S --> T{"Later action?"}
    T -- "Edit log" --> U["Allowed users can update the entry and the system recalculates hours if times changed"]
    T -- "Delete log" --> V["Allowed users can soft-delete the entry so it disappears from normal views but history is preserved"]
    T -- "Nothing else" --> W["Work log remains available for reporting and audit purposes"]
```

## 4. Detailed Alerts, Notifications, and Automation Flow

```mermaid
flowchart TD
    A["Something important happens in the system"] --> B{"What kind of event happened?"}

    B --> C["A project was created or assignments changed"]
    B --> D["A project stage changed"]
    B --> E["A blocker was created"]
    B --> F["A scheduled automation time arrived"]

    C --> G["System emails newly assigned analyst, tester, and developer"]

    D --> H["System writes stage history"]
    H --> I["System writes an automatic timeline update"]
    I --> J["System emails the tester for testing stages, the developer for development stages, and leadership for live stage"]
    J --> K["System creates in-app notifications for project participants and sometimes leadership"]
    K --> L["System checks automation rules that listen to stage changes"]

    E --> M["System saves the blocker"]
    M --> N["System creates in-app notifications for the project owner and analyst"]
    N --> O["Blocker can later be marked resolved"]

    F --> P["Automation processor reads active automation rules"]
    P --> Q{"Does a rule match right now?"}
    Q -- "No" --> R["Write a log or skip the run"]
    Q -- "Yes" --> S["Find the matching projects"]
    S --> T{"What should the automation do?"}
    T -- "Send email" --> U["Build email content and send it to the chosen recipients"]
    T -- "Send notification" --> V["Create in-app notifications for the chosen recipients"]
    U --> W["Write automation run log"]
    V --> W

    G --> X["People receive email in their inbox"]
    J --> X
    U --> X

    K --> Y["Notification center stores the alert"]
    N --> Y
    V --> Y

    Y --> Z["Top bar checks for new notifications every 30 seconds"]
    Z --> AA["Person opens the bell icon, reads the message, and can jump directly to the related project"]
```

## 5. Detailed Reporting and Dashboard Flow

```mermaid
flowchart TD
    A["People open Dashboard or Reports"] --> B["System checks the person's role"]
    B --> C{"Which reporting view should be shown?"}

    C -- "Manager / Analyst Head / Analyst" --> D["Manager-style dashboard"]
    C -- "Senior Developer / Developer / Employee" --> E["Employee-style dashboard"]
    C -- "Report Dashboard" --> F["Full reporting workspace"]
    C -- "Projects Report" --> G["Project summary report"]
    C -- "Workers Report" --> H["Worker summary report"]

    D --> I["Show total projects, active projects, on-hold projects, active blockers, overdue planner items, and recent projects"]
    D --> J["If allowed, also show team activity report filters and results"]

    E --> K["Show my assigned projects"]
    E --> L["Show my pending planner tasks"]
    E --> M["Show blockers created by me"]
    E --> N["Some higher roles also get team activity report access"]

    F --> O["Read project records, planner deadlines, stage info, and total work-log hours"]
    O --> P["Build timeline / gantt view"]
    O --> Q["Build calendar of upcoming deadlines"]
    O --> R["If role allows, also build project-wise work-log summary"]
    O --> S["If role allows, also build team utilization"]
    O --> T["If role allows, also build recent activity feed"]

    G --> U["For each project, show owner, planner progress, active blockers, current stage, total hours, contributor count, and rough progress percent"]
    H --> V["For each team member, show role, active status, project count, current projects, total hours, month hours, and week hours"]

    J --> W["Team activity report can filter by project, person, and date range"]
    N --> W
    W --> X["System reads work logs and returns detailed rows plus total hours"]

    P --> Y["Leadership can see schedule and delivery progress"]
    Q --> Y
    R --> Y
    S --> Y
    T --> Y
    U --> Y
    V --> Y
    X --> Y

    Y --> Z["Reports help management answer:<br/>What is active?<br/>What is blocked?<br/>Who is working on what?<br/>How much time was spent?<br/>What is close to deadline?<br/>Which work is ready to go live?"]
```

## 6. Core Record Map

```mermaid
flowchart TD
    A["Team Members<br/>People who can log in"] --> B["Projects<br/>Main work items"]
    B --> C["Planner Tasks<br/>Ordered task checklist / milestones"]
    B --> D["Project Workers<br/>Owner + contributors"]
    B --> E["Stage History<br/>Every stage movement"]
    B --> F["Project Updates<br/>Manual and system timeline messages"]
    B --> G["Ticket Links<br/>Related ticket numbers or references"]
    B --> H["Blockers<br/>Things stopping progress"]
    B --> I["Transfers<br/>Ownership handover history"]
    B --> J["Attachments<br/>Files uploaded to the project"]
    B --> K["Release Notes<br/>What was delivered or changed"]

    A --> L["Work Logs<br/>Time entries created by people"]
    B --> L

    A --> M["Notifications<br/>In-app alerts sent to one person at a time"]
    B --> M

    N["Automations<br/>Saved business rules"] --> M
    N --> O["Automation Logs<br/>What the automation did or skipped"]

    A --> P["Audit Logs<br/>History of important create / update / delete actions on tracked records"]
    B --> P
    C --> P
    L --> P

    K --> Q["Release-note files and links<br/>Extra evidence and references"]
```

## Practical Reading Order

If a non-technical stakeholder wants to understand the product quickly, read the diagrams in this order:

1. Master Business Flow
2. Detailed Project Lifecycle Flow
3. Detailed Work-Log Flow
4. Detailed Alerts, Notifications, and Automation Flow
5. Detailed Reporting and Dashboard Flow
6. Core Record Map
