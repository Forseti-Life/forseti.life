# Resume Tailoring Module

## ⚠️ **DEPRECATION NOTICE - MODULE CONSOLIDATED**

**This module has been deprecated and consolidated into `job_application_automation`.**

**See [DEPRECATED.md](DEPRECATED.md) for complete migration information.**

---

## 🎯 Overview (Historical)

The Resume Tailoring module streamlines the job application process by automatically generating tailored resumes using AI technology. When a job description is added to the site, it automatically creates a customized resume based on your original resume and the specific job requirements.

## ✨ Features

- **Automatic Resume Tailoring**: Generates tailored resumes when job descriptions are created
- **AI-Powered Optimization**: Uses AWS Bedrock Claude to optimize resumes for specific positions
- **Original Resume Integration**: Maintains a master "Original Resume" for all tailoring operations
- **Job-Specific Customization**: Tailors content based on job title, company, and job description
- **Seamless Integration**: Works automatically in the background without user intervention

## 🔧 Technical Details

### AI Integration
- **Service**: AWS Bedrock Runtime
- **Model**: `anthropic.claude-3-5-sonnet-20240620-v1:0`
- **Region**: `us-west-2`
- **Max Tokens**: 4000 for resume generation

### Core Architecture

#### Hook Implementation
- **`hook_entity_insert()`**: Triggers when Job Description nodes are created
- **Automatic Processing**: No manual intervention required
- **Error Handling**: Graceful fallback if AI service is unavailable

#### Service Class: `ResumeTailoringManager`
Main service responsible for AI integration and resume generation.

**Key Method**: `generateTailoredResume($resume_text, $job_title, $company, $job_description)`

### Content Type Requirements

#### Resume Content Type  
- **Title**: Must be "Original Resume" (exact match)
- **Body**: Contains the master resume content
- **Type**: `resume`

#### Job Posting Content Type (from job_application_automation module)
- **Job Title**: Job title (field: `field_job_title`)
- **Company**: Company reference (field: `field_company_ref`)  
- **Job Description**: Job description content (field: `field_job_description`)
- **Tailored Resume**: AI-generated tailored resume (field: `field_tailored_resume`)
- **Type**: `job_posting`

## 🚀 Installation

1. **Prerequisites**: Install and enable the Job Application Automation module first
2. Enable the Resume Tailoring module in Drupal admin: `/admin/modules`
3. The module will automatically create the Resume content type and add the tailored resume field to job postings
4. Configure AWS credentials for Bedrock access
5. Create the "Original Resume" node
6. Clear cache: `drush cr`

## 📋 Requirements

- Drupal 9, 10, or 11
- Node module
- **Job Application Automation module** (provides job_posting content type)
- AWS SDK for PHP
- AWS Bedrock access with Claude model permissions

## 🔑 Configuration

### AWS Setup
Ensure your server has AWS credentials configured with access to:
- AWS Bedrock Runtime
- Claude 3.5 Sonnet model permissions

### Content Type Setup

#### Resume Content Type (created automatically)
```yaml
Machine name: resume
Fields:
  - title (required)
  - body (long text, required - stores resume content)
```

#### Job Posting Content Type (from job_application_automation module)
```yaml
Machine name: job_posting
Fields:
  - field_job_title (text, required)
  - field_company_ref (entity reference to company, required)
  - field_job_description (long text, required)
  - field_tailored_resume (long text, optional - added by this module)
```

### Original Resume Setup
1. Create a Resume node with title "Original Resume"
2. Add your master resume content to the body field
3. Save the node

## ⚠️ **Important Development Guidelines**

### **Content Type and Field Creation Policy**

**❌ DO NOT create content types or custom fields manually through the Drupal admin interface.**

**✅ ALL content types and custom fields MUST be created programmatically through module install hooks.**

#### **Why This Matters:**
- **Version Control**: Manual changes aren't tracked in code repositories
- **Environment Consistency**: Ensures dev, staging, and production environments match exactly
- **Deployment Safety**: Prevents configuration drift between environments
- **Team Collaboration**: All developers get the same field configurations
- **Rollback Capability**: Changes can be reverted through code versioning

#### **Proper Implementation:**
- Content types are created in `resume_tailoring.install` using `NodeType::create()`
- Custom fields are created using `FieldStorageConfig::create()` and `FieldConfig::create()`
- Install functions include existence checks to prevent duplicate creation errors
- Uninstall functions properly clean up created content types and fields

#### **If You Need Changes:**
1. Update the install functions in `resume_tailoring.install`
2. Create update hooks (e.g., `resume_tailoring_update_9003()`) for existing installations
3. Test the changes by uninstalling/reinstalling the module in development
4. Deploy through standard code deployment processes

## 🏠 **Home Page & Dashboard**

### **Primary Dashboard Location**: `/resume-tailoring`

The Resume Tailoring Dashboard is the central hub for managing your entire resume tailoring workflow. Access it at:
- **Public Dashboard**: `/resume-tailoring` 
- **Admin Dashboard**: `/admin/resume-tailoring`
- **Menu Navigation**: Available in main navigation menu

### **Dashboard Features**
- **5-Step Process Tracker**: Visual progress indicators for each workflow step
- **Authentication Control**: Must be logged in to access full functionality  
- **Progress Statistics**: Count of master resumes, job postings, and tailored resumes
- **Job Postings Table**: List of your job postings with "Generate" buttons
- **Tailored Resumes Table**: View and manage generated tailored resumes
- **Quick Actions**: Direct links to create new content
- **Status Tracking**: Visual indicators for completion status

## 📊 Resume Tailoring Process Flow

### **Complete Workflow Steps**

The Resume Tailoring module follows a structured 5-step process managed from the central dashboard at `/resume-tailoring`:

#### **Step 1: Create User Profile**
- Create an account and complete your user profile
- Ensure you have proper authentication to access resume tailoring features

#### **Step 2: Create Your Master Resume**
- **Content Type**: Resume
- **Requirements**: Comprehensive resume that can be trimmed and tailored
- **Instructions**: Include all your experience, skills, achievements, and education
- **Purpose**: This serves as the source material for all tailored resumes
- **Access**: Must be authenticated to create and view

#### **Step 3: Create Job Posting**
- **Content Type**: Job Posting (existing from job_application_automation module)
- **Purpose**: Store job postings you want to apply for
- **Fields**: Job title, company, full job description, requirements

#### **Step 4: Generate Tailored Resume**
- **Action**: Select a job posting from the dashboard and click "Generate Tailored Resume"
- **Process**: AI analyzes your master resume against the job posting requirements
- **Output**: Creates a new "Tailored Resume" content type linked to the job posting

#### **Step 5: Access Tailored Resume**
- **Content Type**: Tailored Resume
- **Location**: Available via dashboard link next to each job posting
- **Usage**: Copy/download for your job application
- **Management**: Edit, regenerate, or archive as needed

### **Dashboard-Controlled Process**
All steps are managed from the central Resume Tailoring Dashboard (`/resume-tailoring`):
- **Authentication Check**: Must be logged in to proceed
- **Progress Tracking**: Shows completion status of each step
- **Quick Actions**: Direct links to create content for each step
- **Status Overview**: Visual indicators for completed/pending items
- **Generation Controls**: One-click tailored resume generation

## 🔧 **Technical Implementation Standards**

### **Module Installation Process**
The Resume Tailoring module follows Drupal best practices for programmatic content type and field creation:

#### **Content Types Created During Installation:**
1. **Resume** (`resume`) - Master resume storage with comprehensive help text
2. **Tailored Resume** (`tailored_resume`) - AI-generated tailored resumes with status tracking

#### **Custom Fields Created During Installation:**
- `field_job_posting_ref` - Entity reference to job_posting nodes
- `field_original_resume_ref` - Entity reference to original resume nodes  
- `field_tailoring_status` - List field with generation status options
- `field_tailored_resume` - Text field added to job_posting content type

#### **Install Function Features:**
- **Existence Checking**: All functions check for existing content before creation
- **Error Prevention**: Prevents duplicate key errors during installation
- **Logging**: Comprehensive logging of all creation/skipping actions
- **Field Storage Management**: Proper field storage and field config separation
- **Clean Uninstall**: Removes all created content types and fields on uninstall

#### **File Structure:**
```
resume_tailoring/
├── resume_tailoring.install          # Content type and field creation
├── resume_tailoring.module           # Hook implementations
├── resume_tailoring.routing.yml      # URL routing configuration
├── resume_tailoring.libraries.yml    # CSS/JS asset definitions
├── resume_tailoring.links.menu.yml   # Menu link definitions
├── src/Controller/                   # Dashboard controller
├── css/                             # Styling assets
└── js/                              # JavaScript enhancements
```

### **Making Configuration Changes**

#### **Adding New Fields:**
1. Update `_resume_tailoring_create_[content_type]_content_type()` function in `resume_tailoring.install`
2. Add existence checks: `FieldConfig::loadByName()` and `FieldStorageConfig::loadByName()`
3. Create update hook: `function resume_tailoring_update_9004() { ... }`
4. Test by running: `drush updb -y`

#### **Modifying Existing Fields:**
1. Create update hook with field modification logic
2. Use `FieldConfig::loadByName()` to load existing field
3. Update field properties and save
4. Test thoroughly in development environment

#### **Best Practice Commands:**
```bash
# Test clean installation
drush pm:uninstall resume_tailoring -y
drush pm:install resume_tailoring -y

# Run updates for existing installations  
drush updb -y

# Clear caches after changes
drush cr
```

## 🎨 AI Prompt Engineering

The module uses a sophisticated prompt that focuses on:

- **Keyword Optimization**: Incorporates job description keywords
- **Skill Highlighting**: Emphasizes relevant skills and experience
- **Achievement Focus**: Highlights accomplishments that align with the role
- **Format Preservation**: Maintains original resume structure
- **Contact Information**: Keeps personal details unchanged

## 🔍 Logging

The module provides comprehensive logging:

- **Successful Generation**: Logs when resumes are successfully created
- **Error Conditions**: Detailed error messages for troubleshooting
- **API Responses**: Unexpected response format logging
- **Performance Tracking**: Monitor processing times

Access logs: **Reports > Recent log messages > resume_tailoring**

## 🛠️ Troubleshooting

### Common Issues

**Resume Not Generated**
- Verify "Original Resume" node exists with exact title
- Check AWS Bedrock permissions and connectivity
- Review error logs for specific issues
- Ensure required fields are populated

**Incomplete Tailored Resume**
- Check token limits (4000 max)
- Verify job description content is substantial
- Review AI service response in logs

## 🏠 Resume Tailoring Home Page

The Resume Tailoring module provides a dedicated dashboard to manage your resume tailoring workflow:

### **Access the Dashboard**
- **URL**: `/resume-tailoring` or `/admin/resume-tailoring`
- **Navigation**: Admin menu > Resume Tailoring Dashboard

### **Dashboard Features**
- **Resume Management**: View and manage your "Original Resume" node
- **Job Postings**: List all job postings with tailored resumes
- **Tailoring Status**: See which job postings have tailored resumes generated
- **Quick Actions**: Direct links to create resumes and job postings
- **Activity Log**: Recent resume tailoring activities

### **Quick Navigation**
- **Create Original Resume**: `/node/add/resume`
- **Create Job Posting**: `/node/add/job_posting`
- **View All Content**: `/admin/content`
- **Module Logs**: `/admin/reports/dblog` (filter: resume_tailoring)

## 🔄 Customization

### Prompt Modification
Edit `buildResumePrompt()` method in `ResumeTailoringManager.php` to customize AI instructions and focus areas.

### Model Configuration
Change AI model by updating the `modelId` in `ResumeTailoringManager.php`.

## 🚀 Future Enhancements

Potential improvements:
- Support for multiple original resumes
- Industry-specific resume templates
- Cover letter generation
- Integration with job board APIs
- Resume format conversion (PDF, Word, etc.)
