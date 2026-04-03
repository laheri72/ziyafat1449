# Project Overview: Ziyafat us Shukr (ZS1449)

## Purpose
A spiritual and financial progress tracking platform for the Dawoodi Bohra community, enabling Mumineen to record their Amali Khidmat and manage financial targets.

## Core Modules

### 1. Spiritual Tracking (Amali Janib)
- **Quran:** 120 Juz target tracking via AJAX multi-select.
- **Dua:** Activity logging for Tasbih, Namaz, and Duas from a master list.
- **Books:** Book transcription (Istinsakh) progress management.

### 2. Finance Management
- **Waterfall Distribution:** Sequential payment filling (Tasea 66k -> Ashera 97k -> Hadi 127k).
- **Real-time Updates:** AJAX-based contribution entry.

### 3. Super Admin Broadcast Center
- **Mailing System:** Manual batch control (Limit 100/day) for professional reminders.
- **Personalized Content:** Automatically includes individual progress and Jamea-specific insights in every email.
- **Tracking:** Detailed activity log for sent/failed status.

### 4. Admin Utilities
- **User Management:** Full CRUD with secure ITS/TR number tracking.
- **Password Reset:** Super Admin ability to reset any user to their TR number.
- **Reporting:** Cross-category spiritual and financial summaries.

## System Diagram
```mermaid
graph TD
    Admin[Super Admin] --> BC[Broadcast Center]
    Admin --> UM[User Management]
    BC --> SMTP[Hostinger SMTP]
    SMTP --> UserEmail[User Inbox]
    
    User[User] --> Dash[Mobile Dashboard]
    Dash --> Quran[Quran AJAX]
    Dash --> Dua[Dua AJAX]
    Dash --> Finance[Finance Report]
    
    subgraph "Logic Layer"
        Waterfall[Waterfall Logic]
        Insights[Category Insights]
        Toasts[Toast System]
    end
    
    subgraph "Data Layer"
        DB[(MariaDB)]
    end
    
    Logic Layer --> DB
```
