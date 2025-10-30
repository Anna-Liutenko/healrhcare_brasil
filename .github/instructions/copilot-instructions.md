### 1\. Tech Stack

*   **Backend:** PHP 8.2 (no frameworks).
    
*   **Frontend:** "Vanilla" Vue.js 3 (loaded via CDN, no `node_modules`).
    
*   **Server:** XAMPP with Apache and MySQL.
    
*   **Styling:** "Vanilla" CSS (no pre-processors).
    

* * *

### 2\. Constraints (CRITICAL)

*   **AVOID** suggest installing new libraries via Composer or NPM unless explicitly asked.
    
*   **AVOID** use any PHP features newer than version 8.2.
    
*   **AVOID** propose over-engineered solutions (e.g., complex design patterns, factories, or DTOs for simple tasks). **ALWAYS** provide the simplest, "vanilla" solution that fits the current stack.
    
*   **AVOID** suggest refactoring when the request is to fix a bug.
    

* * *

### 3\. Best Practices & Security

*   **Golden Security Rules:**
    
    1.  **Input Validation:** **ALWAYS** validate **ALL** user-provided data on the backend.
        
    2.  **Output Escaping:** **ALWAYS** escape all user-generated output rendered in HTML (use HTMLPurifier).
        
    3.  **Backend Authorization:** **ALWAYS** verify user permissions (role) on the backend before performing any sensitive action (e.g., save, delete, publish).
        
*   **Encoding:** All files and database connections **MUST** use `utf8mb4`.
    
*   **CSS:** Avoid global selectors (except for `body`, `h1`, etc.). Use BEM-like class naming conventions.
    

* * *

### 4\. Environment Configuration

*   **XAMPP Deployment:** The code from the `frontend/` folder is deployed to **TWO** separate server directories:
    
    1.  `C:\xampp\htdocs\visual-editor-standalone\` (for the visual editor functionality)
        
    2.  `C:\xampp\htdocs\healthcare-cms-backend\public\` (for rendering the results of pages created/edited in the visual editor)
        
*   **Important:** When debugging CSS/JS issues in the editor, **ALWAYS** check **both** of these directories.
    

* * *

### 5\. Architecture (Balanced Clean Architecture)

*   **We USE Clean Architecture (CA).** Continue placing code in the correct layers (Application, Infrastructure, Presentation). This is crucial for project stability.
    
*   **HOWEVER, maintain balance.** Avoid over-engineering. If a task is simple (e.g., "add 1 field"), do not create 5 new files. Carefully augment existing files.
    
*   **Priority:** Code simplicity, functionality