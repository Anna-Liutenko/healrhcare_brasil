# 📚 Documentation Index

Quick navigation to all project documentation.

**Last Updated:** October 6, 2025

---

## 🎯 NEW: Hybrid Architecture (Oct 6, 2025)

| Document | Purpose | When to Use |
|----------|---------|-------------|
| [🏛️ Hybrid Architecture Plan](./HYBRID_ARCHITECTURE_PLAN.md) | **Complete architecture documentation** (Static Templates + CMS) | Understanding the full system design |
| [🚀 Quick Start Guide](./HYBRID_ARCHITECTURE_QUICK_START.md) | Step-by-step implementation guide (6 steps, ~11 hours) | Starting development |
| [📊 Architecture Diagrams](./ARCHITECTURE_DIAGRAMS.md) | Visual diagrams: Clean Architecture, sequences, data flow | Visual reference |

---

## 🔴 CRITICAL - Read First

| Document | Purpose | When to Use |
|----------|---------|-------------|
| [XAMPP Sync Antipatterns](./XAMPP_SYNC_ANTIPATTERNS.md) | **MUST READ** - Critical bottleneck issues | Before starting any work |
| [Developer Cheat Sheet](./DEVELOPER_CHEAT_SHEET.md) | Quick reference for daily commands | Keep open while coding |
| [Sync Checklist](./SYNC_CHECKLIST.md) | Pre-test verification checklist | Before testing any feature |

---

## 📊 Project Status

| Document | Purpose | When to Use |
|----------|---------|-------------|
| [Project Status](./PROJECT_STATUS.md) | Current completion status, features, bugs | Check progress and next steps |
| [Session Summary Oct 6 2025](./SESSION_SUMMARY_OCTOBER_6_2025.md) | Latest debugging session results | Understand recent changes |

---

## 🏗️ Setup & Installation

| Document | Purpose | When to Use |
|----------|---------|-------------|
| [README](./README.md) | Main documentation, quick start | First time setup |
| [КАК_ЗАПУСТИТЬ](./КАК_ЗАПУСТИТЬ.md) | Quick start guide (Russian) | Fast setup reference |
| [SETUP_XAMPP](./SETUP_XAMPP.md) | XAMPP configuration details | Setting up development environment |

---

## 📖 Technical Documentation

| Document | Purpose | When to Use |
|----------|---------|-------------|
| [API Endpoints Cheatsheet](./API_ENDPOINTS_CHEATSHEET.md) | All API endpoints reference | Working with backend API |
| [CMS Development Plan](./CMS_DEVELOPMENT_PLAN.md) | Original development roadmap | Understanding architecture |
| [API Contract](./API_CONTRACT.md) | Detailed API specifications | Implementing API features |

---

## 🧪 Testing Documentation

| Document | Purpose | When to Use |
|----------|---------|-------------|
| [✅ E2E Ready to Implement](./E2E_READY_TO_IMPLEMENT.md) | **START HERE** — Overview and navigation | Before starting E2E implementation |
| [E2E Testing Summary](./E2E_TESTING_SUMMARY.md) | Quick overview of E2E testing implementation | Get a quick overview |
| [E2E Implementation Checklist](./E2E_IMPLEMENTATION_CHECKLIST.md) | **Step-by-step checklist** for both tasks | Track progress while implementing |
| [E2E Testing Implementation](./E2E_TESTING_IMPLEMENTATION_PROMPT.md) | **Complete E2E testing guide** (API + Browser UI) | Detailed code and instructions |
| [E2E Quick Start](./E2E_QUICK_START.md) | Commands cheat sheet for running tests | Running tests quickly |

---

## 🐛 Troubleshooting

| Document | Purpose | When to Use |
|----------|---------|-------------|
| [Troubleshooting: Page Save (Oct 6)](./TROUBLESHOOTING_PAGE_SAVE_OCTOBER_2025.md) | Complete debug history for page save issue | Similar "class not found" errors |
| [Debug History](./DEBUG_HISTORY.md) | General debugging log | Looking up past issues |
| [UX Research Report](./UX_RESEARCH_REPORT.md) | User experience analysis | Improving editor UX |

---

## 🗄️ Database

| Document | Purpose | When to Use |
|----------|---------|-------------|
| [Database Schema](../database/DATABASE_SCHEMA.md) | Complete database structure | Understanding data model |
| Migrations | See `database/migrations/` | Setting up or updating database |
| Seeds | See `database/seeds/` | Adding test data |

---

## 🎯 Feature-Specific

| Document | Purpose | When to Use |
|----------|---------|-------------|
| [Menu Editor Prompt](./MENU_EDITOR_PROMPT.md) | Menu editor implementation guide | Building menu management |
| [Auto Menu Deploy Prompt](./AUTO_MENU_DEPLOY_PROMPT.md) | Menu deployment workflow | Setting up menu auto-sync |
| [Editor Improvement Plan](./EDITOR_IMPROVEMENT_PLAN.md) | Editor enhancement roadmap | Planning editor features |

---

## 📋 Archive

| Folder | Purpose |
|--------|---------|
| `docs/archive/` | Old documentation versions |
| `docs/troubleshooting/` | Resolved issue documentation |

---

## 🚀 Quick Links by Task

### "I want to start developing"
1. Read: [XAMPP Sync Antipatterns](./XAMPP_SYNC_ANTIPATTERNS.md)
2. Read: [Developer Cheat Sheet](./DEVELOPER_CHEAT_SHEET.md)
3. Check: [Project Status](./PROJECT_STATUS.md)

### "I got an error"
1. Check: [Sync Checklist](./SYNC_CHECKLIST.md) - Did I sync?
2. Check: [XAMPP Sync Antipatterns](./XAMPP_SYNC_ANTIPATTERNS.md) - Common issues
3. Check: [Troubleshooting: Page Save](./TROUBLESHOOTING_PAGE_SAVE_OCTOBER_2025.md) - Similar errors

### "I need API info"
1. Quick reference: [API Endpoints Cheatsheet](./API_ENDPOINTS_CHEATSHEET.md)
2. Detailed specs: [API Contract](./API_CONTRACT.md)

### "I need database info"
1. Schema: [Database Schema](../database/DATABASE_SCHEMA.md)
2. Migrations: `../database/migrations/`

### "I want to understand the project"
1. Overview: [README](./README.md)
2. Status: [Project Status](./PROJECT_STATUS.md)
3. Architecture: [CMS Development Plan](./CMS_DEVELOPMENT_PLAN.md)

### "I need to test the editor/pages"
1. **START HERE:** [E2E Ready to Implement](./E2E_READY_TO_IMPLEMENT.md)
2. Step-by-step: [E2E Implementation Checklist](./E2E_IMPLEMENTATION_CHECKLIST.md)
3. Quick commands: [E2E Quick Start](./E2E_QUICK_START.md)
4. Full guide: [E2E Testing Implementation](./E2E_TESTING_IMPLEMENTATION_PROMPT.md)

---

## 📝 Documentation Standards

### File Naming
- Use `SCREAMING_SNAKE_CASE.md` for critical docs
- Use `Title_Case.md` for feature docs
- Use lowercase for casual docs

### Structure
- Start with single-line summary
- Use emojis for visual navigation (🔴 = critical, ✅ = checklist, 📚 = reference)
- Include "When to Use" sections
- Link related documents

### Updates
- Always update `PROJECT_STATUS.md` after changes
- Create session summaries for major debug sessions
- Archive outdated docs (don't delete)

---

**Last Updated:** October 8, 2025  
**Total Documents:** 25+ files  
**Most Critical:** XAMPP_SYNC_ANTIPATTERNS.md, DEVELOPER_CHEAT_SHEET.md, SYNC_CHECKLIST.md, E2E_READY_TO_IMPLEMENT.md
