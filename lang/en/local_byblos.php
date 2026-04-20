<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English language strings for local_byblos.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin identity.
$string['pluginname'] = 'Byblos ePortfolio';
$string['pluginname_ascii'] = 'Byblos';

// Capabilities.
$string['byblos:use'] = 'Use Byblos ePortfolio';
$string['byblos:createpage'] = 'Create portfolio pages';
$string['byblos:share'] = 'Share portfolio content';
$string['byblos:sharepublic'] = 'Share portfolio content publicly';
$string['byblos:viewshared'] = 'View shared portfolio content';
$string['byblos:managetemplates'] = 'Manage portfolio templates';
$string['byblos:manageall'] = 'Manage all portfolio content';

// Navigation and headings.
$string['nav_myportfolio'] = 'My Portfolio';
$string['nav_dashboard'] = 'Dashboard';
$string['nav_pages'] = 'Pages';
$string['nav_collections'] = 'Collections';
$string['nav_artefacts'] = 'Artefacts';
$string['nav_shared'] = 'Shared with me';
$string['nav_templates'] = 'Templates';
$string['nav_export'] = 'Export';
$string['nav_course_portfolios'] = 'Course Portfolios';
$string['nav_settings'] = 'Settings';
$string['nav_profile_portfolio'] = 'Portfolio';
$string['dashboard'] = 'My Portfolio';
$string['newpage'] = 'New Page';
$string['editpage'] = 'Edit Page';
$string['viewpage'] = 'View Page';
$string['viewcollection'] = 'View Collection';
$string['managecollection'] = 'Manage Collection';
$string['newartefact'] = 'New Artefact';
$string['editartefact'] = 'Edit Artefact';
$string['viewartefact'] = 'View Artefact';
$string['myartefacts'] = 'My Artefacts';

// Dashboard tabs.
$string['tab_pages'] = 'My Pages';
$string['tab_collections'] = 'My Collections';
$string['tab_artefacts'] = 'My Artefacts';
$string['tab_reviews'] = 'Reviews to do';

// Dashboard widgets.
$string['dashboard_title'] = 'My Portfolio';
$string['dashboard_recent_pages'] = 'Recent pages';
$string['dashboard_recent_artefacts'] = 'Recent artefacts';
$string['dashboard_shared_with_me'] = 'Shared with me';
$string['dashboard_quick_stats'] = 'Quick stats';
$string['dashboard_pages_count'] = '{$a} pages';
$string['dashboard_artefacts_count'] = '{$a} artefacts';
$string['dashboard_collections_count'] = '{$a} collections';
$string['dashboard_no_pages'] = 'You have not created any portfolio pages yet.';
$string['dashboard_no_artefacts'] = 'You have no artefacts yet.';
$string['dashboard_get_started'] = 'Get started by creating your first page.';

// Settings page.
$string['setting_enabled'] = 'Enable plugin';
$string['setting_enabled_desc'] = 'When enabled, users can access the ePortfolio system.';
$string['setting_defaulttheme'] = 'Default page theme';
$string['setting_defaulttheme_desc'] = 'The theme applied to new portfolio pages by default.';
$string['setting_defaultlayout'] = 'Default page layout';
$string['setting_defaultlayout_desc'] = 'The layout applied to new portfolio pages by default.';
$string['setting_allowpublic'] = 'Allow public sharing';
$string['setting_allowpublic_desc'] = 'When enabled, users with the sharepublic capability can generate secret URLs to share portfolios with anyone.';
$string['setting_maxartefacts'] = 'Maximum artefacts per user';
$string['setting_maxartefacts_desc'] = 'The maximum number of artefacts a single user may create. Set to 0 for unlimited.';
$string['setting_maxpages'] = 'Maximum pages per user';
$string['setting_maxpages_desc'] = 'The maximum number of portfolio pages a single user may create. Set to 0 for unlimited.';
$string['setting_allowpdf'] = 'Allow PDF export';
$string['setting_allowpdf_desc'] = 'When enabled, users can export portfolio pages as PDF documents.';
$string['setting_autoimport'] = 'Auto-import artefacts';
$string['setting_autoimport_desc'] = 'When enabled, completed course activities are automatically imported as artefacts.';

// Themes.
$string['theme_clean'] = 'Clean';
$string['theme_academic'] = 'Academic';
$string['theme_modern_dark'] = 'Modern Dark';
$string['theme_creative'] = 'Creative';
$string['theme_corporate'] = 'Corporate';
$string['theme_streaming'] = 'Streaming';

// Layouts.
$string['layout_single'] = 'Single column';
$string['layout_two_equal'] = 'Two equal columns';
$string['layout_two_wide_left'] = 'Two columns (wide left)';
$string['layout_two_wide_right'] = 'Two columns (wide right)';
$string['layout_three_equal'] = 'Three equal columns';
$string['layout_hero_two'] = 'Hero + two columns';

// Section types.
$string['sectiontype_text'] = 'Text';
$string['sectiontype_image'] = 'Image';
$string['sectiontype_file'] = 'File attachment';
$string['sectiontype_video'] = 'Video embed';
$string['sectiontype_reflection'] = 'Reflection';
$string['sectiontype_artefact'] = 'Artefact';
$string['sectiontype_html'] = 'Custom HTML';
$string['sectiontype_heading'] = 'Heading';
$string['sectiontype_divider'] = 'Divider';
$string['sectiontype_gallery'] = 'Image gallery';
$string['sectiontype_badge'] = 'Badges';
$string['sectiontype_competency'] = 'Competencies';
$string['sectiontype_course_summary'] = 'Course summary';
$string['sectiontype_comments'] = 'Comments';

// Artefact types.
$string['artefacttype_file'] = 'File';
$string['artefacttype_text'] = 'Text';
$string['artefacttype_reflection'] = 'Reflection';
$string['artefacttype_link'] = 'External link';
$string['artefacttype_assignment'] = 'Assignment submission';
$string['artefacttype_forum'] = 'Forum post';
$string['artefacttype_quiz'] = 'Quiz attempt';
$string['artefacttype_badge'] = 'Badge';
$string['artefacttype_certificate'] = 'Certificate';
$string['artefacttype_competency'] = 'Competency';
$string['type_all'] = 'All Types';
$string['type_text'] = 'Text';
$string['type_file'] = 'File';
$string['type_image'] = 'Image';
$string['type_badge'] = 'Badge';
$string['type_course_completion'] = 'Course Completion';
$string['type_blog_entry'] = 'Blog Entry';

// Page statuses.
$string['status_draft'] = 'Draft';
$string['status_published'] = 'Published';
$string['status_archived'] = 'Archived';

// Template gallery.
$string['choosetpl'] = 'Choose a template';
$string['choosetpl_desc'] = 'Select a template to start your new page, or start from scratch.';
$string['pagetitle'] = 'Page title';
$string['pagetitle_placeholder'] = 'Enter a title for your page...';
$string['createpage'] = 'Create Page';
$string['startfromscratch'] = 'Start from scratch';
$string['sections'] = '{$a} sections';
$string['section_singular'] = '1 section';
$string['recommended'] = 'Recommended';

// Templates.
$string['template_blank'] = 'Blank Page';
$string['template_blank_desc'] = 'A blank canvas with a single text section. Build your page from the ground up.';
$string['template_about_me'] = 'About Me';
$string['template_about_me_desc'] = 'Introduce yourself with a personal profile, photo, and biography.';
$string['template_learning_journal'] = 'Learning Journal';
$string['template_learning_journal_desc'] = 'Reflect on your learning with structured reflection, key learnings, and next steps.';
$string['template_project_showcase'] = 'Project Showcase';
$string['template_project_showcase_desc'] = 'Present a project with overview, screenshots, methodology, and outcomes.';
$string['template_skills_resume'] = 'Skills & Resume';
$string['template_skills_resume_desc'] = 'Highlight your skills, badges, certifications, and completed courses.';
$string['template_course_reflection'] = 'Course Reflection';
$string['template_course_reflection_desc'] = 'Reflect on a specific course with overview, learnings, evidence, and growth.';
$string['template_research_paper'] = 'Research Paper';
$string['template_research_paper_desc'] = 'Structure a research piece with abstract, introduction, methodology, findings, and conclusion.';
$string['template_creative_portfolio'] = 'Creative Portfolio';
$string['template_creative_portfolio_desc'] = 'Showcase creative work with an artist statement, gallery, and process notes.';
$string['template_academic_profile'] = 'Academic Profile';
$string['template_academic_profile_desc'] = 'Present your academic background, research interests, and publications.';
$string['template_professional_cv'] = 'Professional CV';
$string['template_professional_cv_desc'] = 'A structured curriculum vitae with experience, education, and references.';
$string['template_personal_portfolio'] = 'Personal Portfolio';
$string['template_personal_portfolio_desc'] = 'A versatile portfolio with hero banner, about section, skills, gallery, and social links.';
$string['template_academic_cv'] = 'Academic CV';
$string['template_academic_cv_desc'] = 'Present your academic background with education timeline, research interests, and publications.';
$string['template_creative_work'] = 'Creative Work';
$string['template_creative_work_desc'] = 'Showcase creative projects with artist statement, gallery, exhibitions, and process notes.';
$string['template_learning_journey'] = 'Learning Journey';
$string['template_learning_journey_desc'] = 'Document your learning path with timeline, reflections, badges, and goals.';
$string['template_professional_profile'] = 'Professional Profile';
$string['template_professional_profile_desc'] = 'A corporate-styled profile with summary, experience, skills, and contact information.';
$string['template_research_portfolio'] = 'Research Portfolio';
$string['template_research_portfolio_desc'] = 'Present research with abstract, methodology, findings, bibliography, and data visualisation.';
$string['template_simple_page'] = 'Simple Page';
$string['template_simple_page_desc'] = 'A minimal single-section page. Start from scratch and build as you go.';
$string['invalidtemplate'] = 'Invalid template selected.';

// Pages.
$string['nopages'] = 'You have no portfolio pages yet.';
$string['pagenotfound'] = 'Page not found.';
$string['pagedeleted'] = 'Page deleted.';
$string['pagesaved'] = 'Page saved.';
$string['editthispage'] = 'Edit';
$string['previewthispage'] = 'Preview as viewer';
$string['previewing_as_viewer_banner'] = 'Previewing as viewer — owner chrome is hidden. The nav strip shows the full collection exactly as a shared viewer will experience it. Click any pill to move between pages.';
$string['preview_template'] = 'Preview template';
$string['sharethispage'] = 'Share';
$string['sharethiscollection'] = 'Share collection';
$string['previewthiscollection'] = 'Preview collection';
$string['collection_home_badge'] = 'Home page';
$string['collection_move_up'] = 'Move up';
$string['collection_move_down'] = 'Move down';
$string['collection_make_home'] = 'Make home page';
$string['collection_remove_page'] = 'Remove from collection';
$string['collection_group_hint'] = 'This is a group collection. Every member of the group can add their own pages, reorder, and remove their own contributions. Only the creator can rename or delete it.';
$string['collection_group_personal'] = 'Personal (just me)';
$string['collection_your_contribution'] = 'Your page';
$string['collection_member_badge'] = 'Member';
$string['collection_member_hint'] = 'You can contribute your own pages to this group collection.';
$string['error:notgroupmember'] = 'You are not a member of that group.';
$string['exportthispage'] = 'Export';
$string['deletethispage'] = 'Delete';
$string['confirmdeletepage'] = 'Are you sure you want to delete this page? This action cannot be undone.';
$string['publishpage'] = 'Publish';
$string['reverttodraft'] = 'Revert to draft';
$string['pagepublished'] = 'Page published.';
$string['pagereverted'] = 'Page reverted to draft.';
$string['confirmpublishpage'] = 'Publish this page? Anyone with whom the page is shared will be able to view it.';
$string['confirmreverttodraft'] = 'Revert this page to draft? It will no longer be visible to people you have shared it with.';

// Collections.
$string['newcollection'] = 'New Collection';
$string['collectiontitle'] = 'Collection title';
$string['collectiontitle_placeholder'] = 'Enter a title...';
$string['collectiondesc'] = 'Description (optional)';
$string['createcollection'] = 'Create Collection';
$string['nocollections'] = 'You have no collections yet.';
$string['collectionnotfound'] = 'Collection not found.';
$string['collectiondeleted'] = 'Collection deleted.';
$string['collectionsaved'] = 'Collection saved.';
$string['addpages'] = 'Add Pages';
$string['removepage'] = 'Remove';
$string['nopagesincollection'] = 'This collection has no pages yet.';
$string['availablepages'] = 'Available pages';
$string['addtocollection'] = 'Add to collection';
$string['pagecount'] = '{$a} pages';
$string['deletecollection'] = 'Delete Collection';
$string['confirmdeletecollection'] = 'Are you sure you want to delete this collection? Pages will not be deleted.';

// Artefacts.
$string['noartefacts'] = 'You have no artefacts yet.';
$string['artefactnotfound'] = 'Artefact not found.';
$string['artefactdeleted'] = 'Artefact deleted.';
$string['artefactsaved'] = 'Artefact saved.';
$string['artefacttitle'] = 'Title';
$string['artefacttype'] = 'Type';
$string['artefactdesc'] = 'Description';
$string['artefactcontent'] = 'Content';
$string['saveartefact'] = 'Save Artefact';
$string['deleteartefact'] = 'Delete';
$string['confirmdeleteartefact'] = 'Are you sure you want to delete this artefact?';

// Sharing.
$string['share_user'] = 'Share with user';
$string['share_group'] = 'Share with group';
$string['share_course'] = 'Share with course';
$string['share_institution'] = 'Share with institution';
$string['share_public'] = 'Public link';
$string['share_secret'] = 'Secret URL';
$string['share_token_copied'] = 'Secret link copied to clipboard.';
$string['share_manage'] = 'Manage sharing';
$string['sharetype_label'] = 'Type';
$string['share_already'] = 'already shared';
$string['share_no_users'] = 'No other users are enrolled in your courses.';
$string['share_no_courses'] = 'You are not enrolled in any courses.';
$string['share_no_groups'] = 'You are not a member of any groups in your courses.';
$string['share_public_hint'] = 'A secret URL will be generated. Anyone with the link can view.';
$string['share_revoke'] = 'Revoke access';
$string['share_revoke_confirm'] = 'Are you sure you want to revoke this sharing access?';

// Submission.
$string['submission_submitted'] = 'Submitted';
$string['submission_graded'] = 'Graded';
$string['submission_returned'] = 'Returned';
$string['submission_submit'] = 'Submit portfolio';
$string['submission_submit_confirm'] = 'Once submitted, you will not be able to edit this portfolio until it is returned. Continue?';

// Actions.
$string['action_create_page'] = 'Create page';
$string['action_edit_page'] = 'Edit page';
$string['action_delete_page'] = 'Delete page';
$string['action_duplicate_page'] = 'Duplicate page';
$string['action_create_collection'] = 'Create collection';
$string['action_edit_collection'] = 'Edit collection';
$string['action_delete_collection'] = 'Delete collection';
$string['action_add_section'] = 'Add section';
$string['action_edit_section'] = 'Edit section';
$string['action_delete_section'] = 'Delete section';
$string['action_move_section'] = 'Move section';
$string['action_add_artefact'] = 'Add artefact';
$string['action_delete_artefact'] = 'Delete artefact';
$string['action_export_pdf'] = 'Export as PDF';
$string['action_export_html'] = 'Export as HTML';
$string['action_preview'] = 'Preview';
$string['action_save'] = 'Save';
$string['action_cancel'] = 'Cancel';
$string['action_confirm'] = 'Confirm';

// Events.
$string['event_page_created'] = 'Portfolio page created';
$string['event_page_viewed'] = 'Portfolio page viewed';
$string['event_page_shared'] = 'Portfolio page shared';
$string['event_artefact_created'] = 'Artefact created';
$string['event_portfolio_exported'] = 'Portfolio exported';

// Error messages.
$string['error_plugin_disabled'] = 'The portfolio system is currently disabled.';
$string['error_page_not_found'] = 'Portfolio page not found.';
$string['error_collection_not_found'] = 'Collection not found.';
$string['error_artefact_not_found'] = 'Artefact not found.';
$string['error_no_permission'] = 'You do not have permission to perform this action.';
$string['error_max_pages_reached'] = 'You have reached the maximum number of pages ({$a}).';
$string['error_max_artefacts_reached'] = 'You have reached the maximum number of artefacts ({$a}).';
$string['error_invalid_share_type'] = 'Invalid sharing type.';
$string['error_public_sharing_disabled'] = 'Public sharing is disabled by the administrator.';
$string['error_invalid_share_value'] = 'That target is not in your scope. Pick a course, group, or user you are enrolled with.';
$string['error_page_submitted'] = 'This page has been submitted for grading and cannot be edited.';
$string['error_invalid_layout'] = 'Invalid layout selected.';
$string['error_invalid_theme'] = 'Invalid theme selected.';
$string['error_file_too_large'] = 'The uploaded file exceeds the maximum allowed size.';
$string['error_invalid_file_type'] = 'This file type is not allowed.';
$string['error_export_failed'] = 'Export failed. Please try again.';
$string['invalidaction'] = 'Invalid action.';
$string['accessdenied'] = 'You do not have permission to perform this action.';

// Privacy.
$string['privacy:metadata:local_byblos_artefact'] = 'Stores user-created portfolio artefacts (text, files, reflections, etc.).';
$string['privacy:metadata:local_byblos_artefact:userid'] = 'The ID of the user who created the artefact.';
$string['privacy:metadata:local_byblos_artefact:title'] = 'The title of the artefact.';
$string['privacy:metadata:local_byblos_artefact:description'] = 'A description of the artefact.';
$string['privacy:metadata:local_byblos_artefact:content'] = 'The content body of the artefact.';
$string['privacy:metadata:local_byblos_artefact:timecreated'] = 'When the artefact was created.';
$string['privacy:metadata:local_byblos_artefact:timemodified'] = 'When the artefact was last modified.';
$string['privacy:metadata:local_byblos_page'] = 'Stores user-created portfolio pages.';
$string['privacy:metadata:local_byblos_page:userid'] = 'The ID of the user who created the page.';
$string['privacy:metadata:local_byblos_page:title'] = 'The title of the page.';
$string['privacy:metadata:local_byblos_page:description'] = 'A description of the page.';
$string['privacy:metadata:local_byblos_page:timecreated'] = 'When the page was created.';
$string['privacy:metadata:local_byblos_page:timemodified'] = 'When the page was last modified.';
$string['privacy:metadata:local_byblos_collection'] = 'Stores user-created portfolio collections.';
$string['privacy:metadata:local_byblos_collection:userid'] = 'The ID of the user who created the collection.';
$string['privacy:metadata:local_byblos_collection:title'] = 'The title of the collection.';
$string['privacy:metadata:local_byblos_collection:description'] = 'A description of the collection.';
$string['privacy:metadata:local_byblos_collection:timecreated'] = 'When the collection was created.';
$string['privacy:metadata:local_byblos_collection:timemodified'] = 'When the collection was last modified.';
$string['privacy:metadata:local_byblos_artefact:artefacttype'] = 'The type of artefact (text, file, badge, etc.).';

$string['privacy:metadata:local_byblos_page:layoutkey'] = 'The layout key used for the page.';
$string['privacy:metadata:local_byblos_page:themekey'] = 'The theme key used for the page.';
$string['privacy:metadata:local_byblos_page:status'] = 'The publication status of the page (draft, published, archived).';

$string['privacy:metadata:local_byblos_section'] = 'Stores sections (content blocks) within portfolio pages.';
$string['privacy:metadata:local_byblos_section:pageid'] = 'The ID of the page this section belongs to.';
$string['privacy:metadata:local_byblos_section:sectiontype'] = 'The type of section (text, image gallery, etc.).';
$string['privacy:metadata:local_byblos_section:content'] = 'The content body of the section.';
$string['privacy:metadata:local_byblos_section:configdata'] = 'JSON configuration data for the section.';
$string['privacy:metadata:local_byblos_section:timecreated'] = 'When the section was created.';
$string['privacy:metadata:local_byblos_section:timemodified'] = 'When the section was last modified.';

$string['privacy:metadata:local_byblos_collection_page'] = 'Maps pages to collections with sort order.';
$string['privacy:metadata:local_byblos_collection_page:collectionid'] = 'The collection ID.';
$string['privacy:metadata:local_byblos_collection_page:pageid'] = 'The page ID.';
$string['privacy:metadata:local_byblos_collection_page:sortorder'] = 'The display order of the page within the collection.';

$string['privacy:metadata:local_byblos_share'] = 'Stores sharing rules for pages and collections.';
$string['privacy:metadata:local_byblos_share:pageid'] = 'The shared page ID (if page share).';
$string['privacy:metadata:local_byblos_share:collectionid'] = 'The shared collection ID (if collection share).';
$string['privacy:metadata:local_byblos_share:sharetype'] = 'The type of share (user, course, group, public).';
$string['privacy:metadata:local_byblos_share:sharevalue'] = 'The target ID for the share (user ID, course ID, group ID).';
$string['privacy:metadata:local_byblos_share:token'] = 'The secret token for public shares.';
$string['privacy:metadata:local_byblos_share:timecreated'] = 'When the share was created.';

$string['privacy:metadata:local_byblos_page_course'] = 'Tags pages to courses for course-level portfolio views.';
$string['privacy:metadata:local_byblos_page_course:pageid'] = 'The tagged page ID.';
$string['privacy:metadata:local_byblos_page_course:courseid'] = 'The course ID the page is tagged to.';
$string['privacy:metadata:local_byblos_page_course:timecreated'] = 'When the tag was created.';

$string['privacy:metadata:local_byblos_submission'] = 'Stores portfolio submissions to assignments.';
$string['privacy:metadata:local_byblos_submission:userid'] = 'The ID of the user who submitted.';
$string['privacy:metadata:local_byblos_submission:pageid'] = 'The submitted page ID.';
$string['privacy:metadata:local_byblos_submission:collectionid'] = 'The submitted collection ID.';
$string['privacy:metadata:local_byblos_submission:assignmentid'] = 'The assignment this submission is linked to.';
$string['privacy:metadata:local_byblos_submission:status'] = 'The submission status.';
$string['privacy:metadata:local_byblos_submission:timecreated'] = 'When the submission was made.';

// Completion.
$string['completion_pages'] = 'Minimum portfolio pages for completion';
$string['completion_pages_desc'] = 'The minimum number of portfolio pages a student must create for completion criteria. Set to 0 to disable.';

// Section editor page.
$string['editpagetitle'] = 'Edit: {$a}';
$string['pagesettings'] = 'Page Settings';
$string['theme'] = 'Theme';
$string['addsection'] = 'Add Section';
$string['choosesectiontype'] = 'Choose section type';
$string['nosections'] = 'This page has no sections yet. Click "Add Section" to get started.';
$string['deletesectionconfirm'] = 'Remove this section?';

// Section types (12 from MoodleGo).
$string['sectiontype_hero'] = 'Hero Banner';
$string['sectiontype_hero_desc'] = 'Full-width banner with background, name, title';
$string['sectiontype_text'] = 'Text';
$string['sectiontype_text_desc'] = 'Heading and rich text body';
$string['sectiontype_text_image'] = 'Text + Image';
$string['sectiontype_text_image_desc'] = 'Two columns: text and image';
$string['sectiontype_gallery'] = 'Gallery';
$string['sectiontype_gallery_desc'] = 'Grid of image/artefact cards';
$string['sectiontype_skills'] = 'Skills';
$string['sectiontype_skills_desc'] = 'Progress bar chart of skills';
$string['sectiontype_timeline'] = 'Timeline';
$string['sectiontype_timeline_desc'] = 'Vertical timeline of events';
$string['sectiontype_badges'] = 'Badges';
$string['sectiontype_badges_desc'] = 'Auto-imported badge cards';
$string['sectiontype_completions'] = 'Completions';
$string['sectiontype_completions_desc'] = 'Course completion cards';
$string['sectiontype_social'] = 'Social Links';
$string['sectiontype_social_desc'] = 'Social media icon links';
$string['sectiontype_cta'] = 'Call to Action';
$string['sectiontype_cta_desc'] = 'Banner with heading, text, and button';
$string['sectiontype_divider'] = 'Divider';
$string['sectiontype_divider_desc'] = 'Thin line or spacer';
$string['sectiontype_custom'] = 'Custom HTML';
$string['sectiontype_custom_desc'] = 'Freeform rich HTML block';
$string['sectiontype_chart'] = 'Chart';
$string['sectiontype_chart_desc'] = 'SVG bar, line, pie or donut chart';
$string['sectiontype_cloud'] = 'Word Cloud';
$string['sectiontype_cloud_desc'] = 'Weighted word cloud block';
$string['sectiontype_quote'] = 'Pull Quote';
$string['sectiontype_quote_desc'] = 'Featured quotation with attribution';
$string['sectiontype_stats'] = 'Stats';
$string['sectiontype_stats_desc'] = 'Row of big-number stat cards';
$string['sectiontype_citations'] = 'Citations';
$string['sectiontype_citations_desc'] = 'Numbered academic reference list';
$string['sectiontype_files'] = 'Files';
$string['sectiontype_files_desc'] = 'A list of downloadable files — list, tile, or thumbnail display';
$string['sectiontype_youtube'] = 'YouTube video';
$string['sectiontype_youtube_desc'] = 'Embed a YouTube video from its URL';
$string['sectiontype_pagenav'] = 'Page navigation';
$string['sectiontype_pagenav_desc'] = 'Tabs, pills, cards or prev/next linking to other portfolio pages';

// Page navigation section.
$string['pagenav_heading'] = 'Heading';
$string['pagenav_source'] = 'Source';
$string['pagenav_source_collection'] = 'Collection';
$string['pagenav_source_manual'] = 'Manual list of pages';
$string['pagenav_collection'] = 'Collection';
$string['pagenav_display'] = 'Display';
$string['pagenav_display_tabs'] = 'Tabs';
$string['pagenav_display_pills'] = 'Pills';
$string['pagenav_display_cards'] = 'Cards';
$string['pagenav_display_nextprev'] = 'Previous / next';
$string['pagenav_show_descriptions'] = 'Show page descriptions on cards';
$string['pagenav_pages'] = 'Pages';
$string['pagenav_addpage'] = 'Add page';
$string['pagenav_pickpage'] = 'Pick a page';
$string['pagenav_empty'] = 'No pages to show.';
$string['pagenav_viewpage'] = 'View page';
$string['pagenav_previous'] = 'Previous';
$string['pagenav_next'] = 'Next';
$string['pagenav_nocollections'] = 'You do not have any collections yet.';
$string['pagenav_loadingcollections'] = 'Loading collections...';
$string['pagenav_loadingpages'] = 'Loading your pages...';

// Collection control (editor header).
$string['collection_none'] = 'No collection';
$string['collection_control_primary'] = 'Primary';
$string['collection_control_add_new'] = 'Create new collection';
$string['collection_control_newtitle'] = 'Collection title';
$string['collection_control_create'] = 'Create';
$string['collection_control_loading'] = 'Loading collections...';
$string['collection_control_empty'] = 'You do not have any collections yet.';

// Error strings (section editor).
$string['error:pagenotfound'] = 'Page not found';
$string['error:nopermission'] = 'You do not have permission to edit this page';
$string['error:invalidsectiontype'] = 'Invalid section type';
$string['error:sectionnotfound'] = 'Section not found';
$string['error:invalidparam'] = 'Invalid parameter';
$string['myreviews_nav'] = 'My peer reviews';
$string['edittitle_tooltip'] = 'Click to rename this page';
$string['peerbanner_heading'] = 'Peer reviews assigned to you ({$a->pending} pending · {$a->completed} complete)';
$string['peerbanner_review_link'] = 'Review {$a->name}\'s portfolio';
$string['peerbanner_waiting'] = 'Waiting for {$a} to submit…';
$string['peerbanner_allcomplete'] = 'All your assigned reviews are done. Nice work.';
$string['peerbanner_viewall'] = 'View the {$a} review(s) you\'ve already submitted';
$string['error:submissionnotfound'] = 'Submission not found';
$string['error:commentnotfound'] = 'Comment not found';
$string['error:invalidtitle'] = 'Please provide a title';
$string['error:pagenotincollection'] = 'This page is not part of that collection';

// Assessment review viewer.
$string['reviewing_as'] = 'Reviewing as {$a}';
$string['role_self'] = 'the author (read-only)';
$string['role_teacher'] = 'teacher';
$string['role_peer'] = 'peer reviewer';
$string['addcomment'] = 'Add comment';
$string['savecomment'] = 'Save';
$string['cancelcomment'] = 'Cancel';
$string['editcomment'] = 'Edit';
$string['deletecomment'] = 'Delete';
$string['confirmdeletecomment'] = 'Delete this comment?';
$string['nocommentsyet'] = 'No comments yet.';
$string['commentplaceholder'] = 'Write a comment\u2026';
$string['pagelevelcomments'] = 'Overall comments on this portfolio';
$string['sectioncomments'] = 'Section comments';

// Auto-import.
$string['importedbadges'] = 'Imported {$a} new badge(s).';
$string['importedcompletions'] = 'Imported {$a} new course completion(s).';

// Renderer empty-state strings.
$string['emptytext'] = 'Click Edit to add content...';
$string['addimage'] = 'Add an image';
$string['emptygallery'] = 'No gallery items yet. Click Edit to add artefacts or images.';
$string['noskills'] = 'No skills added yet.';
$string['notimeline'] = 'No timeline entries yet.';
$string['nobadges'] = 'No badges earned yet. Complete activities to earn badges.';
$string['nocompletions'] = 'No course completions yet.';
$string['nosocial'] = 'Add your social links in the editor.';
$string['learnmore'] = 'Learn More';
$string['emptycustom'] = 'Empty custom section. Click Edit to add HTML content.';
$string['unknownsectiontype'] = 'Unknown section type: {$a}';
$string['skills'] = 'Skills';
$string['timeline'] = 'Timeline';
$string['badges'] = 'Badges';
$string['completions'] = 'Completions';
$string['startediting'] = 'Start editing';

// Academic section empty-state and label strings.
$string['nochart'] = 'No chart data yet. Click Edit to add items.';
$string['nocloud'] = 'No words in the cloud yet. Click Edit to add some.';
$string['emptyquote'] = 'Empty quote. Click Edit to add content.';
$string['nostats'] = 'No stat cards yet. Click Edit to add some.';
$string['nocitations'] = 'No citations yet. Click Edit to add references.';
$string['citations_default_heading'] = 'References';
$string['nofiles'] = 'No files yet. Click Edit to add some.';
$string['files_default_heading'] = 'Files';
$string['files_heading'] = 'Heading';
$string['files_display'] = 'Display';
$string['files_display_list'] = 'List';
$string['files_display_tile'] = 'Tile';
$string['files_display_thumbs'] = 'Thumbnails';
$string['files_items'] = 'Files';
$string['files_url'] = 'URL';
$string['files_title'] = 'Title (optional)';
$string['files_description'] = 'Description (optional)';
$string['files_type'] = 'Type hint (optional — e.g. pdf, image)';
$string['files_add'] = 'Add file';
$string['youtube_url'] = 'YouTube URL';
$string['youtube_heading'] = 'Heading (optional)';
$string['youtube_description'] = 'Caption (optional)';
$string['youtube_start'] = 'Start at (seconds, optional)';
$string['youtube_invalid'] = 'Enter a valid YouTube URL — e.g. https://www.youtube.com/watch?v=… or https://youtu.be/…';
$string['youtube_alignment'] = 'Layout';
$string['youtube_align_full'] = 'Full width';
$string['youtube_align_center'] = 'Center';
$string['youtube_align_left'] = 'Align left — text on right';
$string['youtube_align_right'] = 'Align right — text on left';
$string['youtube_body'] = 'Body text (left/right layouts only)';
$string['youtube_body_placeholder'] = 'Add a description of this video alongside it.';

// Chart editor labels.
$string['chart_heading'] = 'Heading';
$string['chart_type'] = 'Chart type';
$string['chart_type_bar'] = 'Bar';
$string['chart_type_line'] = 'Line';
$string['chart_type_pie'] = 'Pie';
$string['chart_type_donut'] = 'Donut';
$string['chart_color'] = 'Base colour';
$string['chart_items'] = 'Data points';
$string['chart_item_label'] = 'Label';
$string['chart_item_value'] = 'Value';
$string['chart_add_item'] = 'Add data point';

// Cloud editor labels.
$string['cloud_heading'] = 'Heading';
$string['cloud_color'] = 'Base colour';
$string['cloud_items'] = 'Words';
$string['cloud_word'] = 'Word';
$string['cloud_weight'] = 'Weight (1–10)';
$string['cloud_add_word'] = 'Add word';

// Quote editor labels.
$string['quote_body'] = 'Quote body';
$string['quote_attribution'] = 'Attribution';
$string['quote_source'] = 'Source URL (optional)';

// Stats editor labels.
$string['stats_heading'] = 'Heading';
$string['stats_items'] = 'Stat cards (2–4)';
$string['stats_number'] = 'Number';
$string['stats_label'] = 'Label';
$string['stats_description'] = 'Description';
$string['stats_add_item'] = 'Add stat card';

// Citations editor labels.
$string['citations_heading'] = 'Heading';
$string['citations_style'] = 'Citation style';
$string['citations_style_apa'] = 'APA';
$string['citations_style_mla'] = 'MLA';
$string['citations_style_chicago'] = 'Chicago';
$string['citations_style_plain'] = 'Plain';
$string['citations_items'] = 'References';
$string['citations_text'] = 'Citation text';
$string['citations_url'] = 'URL (optional)';
$string['citations_add_item'] = 'Add reference';

// Layout descriptions.
$string['layout_single_desc'] = 'Full-width single column layout';
$string['layout_two_equal_desc'] = '50/50 two-column layout';
$string['layout_two_wide_left_desc'] = '67/33 two-column layout with wider left';
$string['layout_two_wide_right_desc'] = '33/67 two-column layout with wider right';
$string['layout_three_equal_desc'] = '33/33/33 three-column layout';
$string['layout_hero_two_desc'] = 'Full-width hero block on top, two equal columns below';

// Theme descriptions.
$string['theme_clean_desc'] = 'White background, system fonts, subtle shadows';
$string['theme_academic_desc'] = 'Cream background, serif headings, navy accents';
$string['theme_modern_dark_desc'] = 'Dark background, vibrant accents, card-based';
$string['theme_creative_desc'] = 'White background, bold purple headers, gradient sections';
$string['theme_corporate_desc'] = 'Tight spacing, blue accents, minimal and professional';
$string['theme_streaming_desc'] = 'Cinematic dark background, teal accents, large typography';

// Collection/page view.
$string['editcollection'] = 'Edit Collection';
$string['nocollectionpages'] = 'This collection has no pages yet.';
$string['sharepage'] = 'Share';

// Miscellaneous.
$string['confirm_delete'] = 'Are you sure you want to delete this item? This action cannot be undone.';
$string['no_results'] = 'No results found.';
$string['loading'] = 'Loading...';
$string['last_modified'] = 'Last modified: {$a}';
$string['created_on'] = 'Created: {$a}';
$string['by_user'] = 'by {$a}';
$string['page_of'] = 'Page {$a->current} of {$a->total}';
$string['sort_title'] = 'Sort by title';
$string['sort_date'] = 'Sort by date';
$string['sort_modified'] = 'Sort by last modified';
$string['filter_all'] = 'All';
$string['filter_draft'] = 'Drafts';
$string['filter_published'] = 'Published';

// Phase 5: advisory checklist (editor sidebar).
$string['checklist_heading'] = 'Assessment checklist';
$string['checklist_toggle'] = 'Checklist';
$string['checklist_none'] = 'No active assignments with a checklist for this page.';
$string['checklist_pick'] = 'Show checklist for:';
$string['checklist_due'] = 'Due {$a}';
$string['checklist_hint'] = 'Items below are guidance only — they are not enforced when you submit.';
$string['error:pagenotowned'] = 'This page does not belong to you.';

// Peer review workflow (Phase 4).
$string['peerassign_title'] = 'Manage peer reviewers';
$string['peerassign_manual'] = 'Assign a reviewer manually';
$string['peerassign_random'] = 'Auto-assign reviewers randomly';
$string['peerassign_group'] = 'Assign reviewers within Moodle groups';
$string['peerassign_added'] = 'Peer assignment saved.';
$string['peerassign_removed'] = 'Peer assignment removed.';
$string['peerassign_noreviewees'] = 'No students have submitted a portfolio yet.';
$string['peerassign_noassignments'] = 'No reviewers assigned yet.';
$string['peerassign_addreviewer'] = 'Add reviewer';
$string['peerassign_autoassign'] = 'Auto-assign';
$string['peerassign_assigngroups'] = 'Assign by group';
$string['peerassign_peersperstudent'] = 'Reviewers per student';
$string['peerassign_remove'] = 'Remove';
$string['peerassign_currentassignments'] = 'Current peer assignments';
$string['col_reviewer'] = 'Reviewer';
$string['col_reviewee'] = 'Reviewee';
$string['col_reviewers'] = 'Reviewers';
$string['peerstatus_pending'] = 'Pending';
$string['peerstatus_complete'] = 'Complete';
$string['peerstatus_declined'] = 'Declined';

// Reviews to do tab.
$string['reviews_empty'] = 'You have no peer reviews to complete right now.';
$string['reviews_open'] = 'Open review';
$string['reviews_assignment'] = 'Assignment';
$string['reviews_reviewee'] = 'Portfolio author';
$string['reviews_assigned'] = 'Assigned';

// Peer review submission panel.
$string['peerreview_submit_panel'] = 'Submit peer review';
$string['peerreview_submit_btn'] = 'Submit review';
$string['peerreview_submitted'] = 'Review submitted. Thank you!';
$string['peerreview_already_submitted'] = 'You have already submitted this review.';
$string['peerreview_score_numeric'] = 'Advisory score (0–100)';
$string['peerreview_score_stars'] = 'Star rating (1–5)';
$string['peerreview_score_none'] = 'Comments only — no score required.';
$string['peerreview_rubric_todo'] = 'Advanced rubric input (future: rubric form)';
$string['peerreview_rubric_label'] = 'Rubric data (JSON)';
$string['peerreview_rubric_missing'] = 'Rubric grading is selected for this assignment, but no rubric has been defined yet. Paste the raw JSON payload below, or ask the teacher to publish a rubric definition.';
$string['peerreview_score_readonly'] = 'Advisory score: {$a}';
$string['peerreview_error_generic'] = 'Failed to submit review. Please try again.';

// Message provider.
$string['messageprovider:peerreviewcomplete'] = 'Peer review submitted on your portfolio';
$string['msg_peerreviewcomplete_subject'] = 'A peer has reviewed your portfolio';
$string['msg_peerreviewcomplete_body']    = '{$a->reviewer} has submitted a peer review of your portfolio for "{$a->assignment}".';

// Errors.
$string['error:peernotfound'] = 'Peer review assignment not found.';
