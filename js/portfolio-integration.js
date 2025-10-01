/**
 * Dynamic Portfolio Integration
 * Load GitHub projects dynamically into portfolio
 */

// Portfolio configuration
const PORTFOLIO_CONFIG = {
  maxDevelopmentProjects: 4,
  loadingText: 'Projecten laden...',
  errorText: 'Kon projecten niet laden',
  fallbackProjects: [
    {
      name: 'Portfolio Website',
      description: 'Responsieve portfolio website met moderne animaties',
      language: 'HTML/CSS',
      url: '#',
      year: '2024'
    },
    {
      name: 'Web Application',
      description: 'Moderne webapplicatie met interactieve functionaliteiten',
      language: 'JavaScript',
      url: '#',
      year: '2024'
    }
  ]
};

// Create fallback project HTML
function createFallbackProjectHTML(project) {
  return `
    <div class="grid-item development" data-aos="fade-up">
      <div class="grid-item-wrapper">
        <div class="portfolio-badges">
          <span class="badge badge-development">Development</span>
          <span class="badge badge-tool">${project.language}</span>
          <span class="badge badge-date">${project.year}</span>
        </div>
        <img
          src="img/Logo.png"
          alt="portfolio-img"
          class="portfolio-item"
        />
        <div class="grid-info">
          <div class="quick-info">
            <div class="info-content">
              <h5>${project.name}</h5>
              <p>${project.description}</p>
            </div>
          </div>
          <div class="grid-link d-flex justify-content-center">
            <button class="btn btn-primary btn-sm">
              Meer Informatie
            </button>
          </div>
        </div>
      </div>
    </div>
  `;
}

// Show loading state
function showLoadingState() {
  const container = document.querySelector('.grid-portfolio');
  if (!container) return;
  
  const loadingHTML = `
    <div class="grid-item development loading-item" data-aos="fade-up">
      <div class="grid-item-wrapper">
        <div class="loading-content">
          <div class="loading-spinner"></div>
          <p>${PORTFOLIO_CONFIG.loadingText}</p>
        </div>
      </div>
    </div>
  `;
  
  // Insert loading items
  for (let i = 0; i < PORTFOLIO_CONFIG.maxDevelopmentProjects; i++) {
    container.insertAdjacentHTML('afterbegin', loadingHTML);
  }
}

// Remove loading state
function removeLoadingState() {
  const loadingItems = document.querySelectorAll('.loading-item');
  loadingItems.forEach(item => item.remove());
}

// Insert GitHub projects into portfolio
async function insertGitHubProjects() {
  const container = document.querySelector('.grid-portfolio');
  if (!container) {
    console.error('Portfolio container not found');
    return;
  }
  
  try {
    showLoadingState();
    
    // Fetch GitHub data
    const projectsData = await initializeGitHubIntegration();
    
    // Remove loading state
    removeLoadingState();
    
    if (projectsData.length === 0) {
      // Use fallback projects if GitHub fails
      console.warn('No GitHub projects found, using fallback projects');
      insertFallbackProjects();
      return;
    }
    
    // Insert GitHub projects (limit to max projects)
    const projectsToShow = projectsData.slice(0, PORTFOLIO_CONFIG.maxDevelopmentProjects);
    
    projectsToShow.forEach(({ repo, languages }, index) => {
      const projectHTML = createDevelopmentProjectHTML(repo, languages);
      
      // Insert at the beginning (newest first)
      const existingDevItems = container.querySelectorAll('.grid-item.development');
      if (existingDevItems.length > 0) {
        existingDevItems[0].insertAdjacentHTML('beforebegin', projectHTML);
      } else {
        const firstDesignItem = container.querySelector('.grid-item.design');
        if (firstDesignItem) {
          firstDesignItem.insertAdjacentHTML('beforebegin', projectHTML);
        } else {
          container.insertAdjacentHTML('afterbegin', projectHTML);
        }
      }
    });
    
    // Re-initialize Isotope if available
    if (window.jQuery && jQuery('.grid-portfolio').data('isotope')) {
      jQuery('.grid-portfolio').isotope('reloadItems').isotope();
    }
    
    // Re-initialize AOS for new items
    if (window.AOS) {
      AOS.refresh();
    }
    
    console.log(`Successfully loaded ${projectsToShow.length} GitHub projects`);
    
  } catch (error) {
    console.error('Error loading GitHub projects:', error);
    removeLoadingState();
    insertFallbackProjects();
  }
}

// Insert fallback projects
function insertFallbackProjects() {
  const container = document.querySelector('.grid-portfolio');
  if (!container) return;
  
  PORTFOLIO_CONFIG.fallbackProjects.forEach(project => {
    const projectHTML = createFallbackProjectHTML(project);
    container.insertAdjacentHTML('afterbegin', projectHTML);
  });
  
  // Re-initialize Isotope if available
  if (window.jQuery && jQuery('.grid-portfolio').data('isotope')) {
    jQuery('.grid-portfolio').isotope('reloadItems').isotope();
  }
}

// Update development skills based on GitHub data
async function updateDevelopmentSkills() {
  try {
    console.log('🔄 Updating development skills from GitHub languages...');
    
    // Fetch repositories directly
    const repositories = await fetchAllRepositories();
    
    if (!repositories || repositories.length === 0) {
      console.warn('⚠️ No GitHub repositories found for skills update');
      return;
    }
    
    // Collect all languages from all repositories
    const allLanguages = {};
    let totalBytes = 0;
    
    // Process repositories in batches to avoid rate limiting
    const batchSize = 5;
    for (let i = 0; i < repositories.length; i += batchSize) {
      const batch = repositories.slice(i, i + batchSize);
      
      const languagePromises = batch.map(async (repo) => {
        try {
          const languages = await fetchRepositoryLanguages(repo.name);
          return { repoName: repo.name, languages };
        } catch (error) {
          console.warn(`⚠️ Could not fetch languages for ${repo.name}:`, error.message);
          return { repoName: repo.name, languages: {} };
        }
      });
      
      const batchResults = await Promise.all(languagePromises);
      
      // Aggregate language data
      batchResults.forEach(({ languages }) => {
        Object.entries(languages).forEach(([lang, bytes]) => {
          allLanguages[lang] = (allLanguages[lang] || 0) + bytes;
          totalBytes += bytes;
        });
      });
      
      // Small delay between batches
      if (i + batchSize < repositories.length) {
        await new Promise(resolve => setTimeout(resolve, 200));
      }
    }
    
    if (totalBytes === 0) {
      console.warn('⚠️ No language data found in repositories');
      return;
    }
    
    // Calculate percentages and get top languages
    const languagePercentages = Object.entries(allLanguages)
      .map(([lang, bytes]) => ({
        language: lang,
        percentage: Math.round((bytes / totalBytes) * 100),
        bytes: bytes
      }))
      .sort((a, b) => b.bytes - a.bytes)
      .slice(0, 6); // Top 6 languages
    
    // Language mapping for better display names and colors
    const languageMap = {
      'JavaScript': { name: 'JavaScript', color: '#f1e05a', icon: 'fab fa-js-square' },
      'TypeScript': { name: 'TypeScript', color: '#2b7489', icon: 'fab fa-js-square' },
      'Python': { name: 'Python', color: '#3572a5', icon: 'fab fa-python' },
      'HTML': { name: 'HTML', color: '#e34c26', icon: 'fab fa-html5' },
      'CSS': { name: 'CSS', color: '#1572b6', icon: 'fab fa-css3-alt' },
      'PHP': { name: 'PHP', color: '#4f5d95', icon: 'fab fa-php' },
      'Java': { name: 'Java', color: '#b07219', icon: 'fab fa-java' },
      'C#': { name: 'C#', color: '#239120', icon: 'fas fa-code' },
      'C++': { name: 'C++', color: '#f34b7d', icon: 'fas fa-code' },
      'C': { name: 'C', color: '#555555', icon: 'fas fa-code' },
      'Go': { name: 'Go', color: '#00add8', icon: 'fas fa-code' },
      'Rust': { name: 'Rust', color: '#dea584', icon: 'fas fa-code' },
      'Swift': { name: 'Swift', color: '#ffac45', icon: 'fab fa-swift' },
      'Kotlin': { name: 'Kotlin', color: '#a97bff', icon: 'fas fa-code' },
      'Dart': { name: 'Dart', color: '#00b4ab', icon: 'fas fa-code' },
      'Vue': { name: 'Vue.js', color: '#4fc08d', icon: 'fab fa-vuejs' },
      'React': { name: 'React', color: '#61dafb', icon: 'fab fa-react' },
      'Angular': { name: 'Angular', color: '#dd0031', icon: 'fab fa-angular' },
      'Node.js': { name: 'Node.js', color: '#339933', icon: 'fab fa-node-js' },
      'Shell': { name: 'Shell/Bash', color: '#89e051', icon: 'fas fa-terminal' },
      'PowerShell': { name: 'PowerShell', color: '#012456', icon: 'fas fa-terminal' },
      'SQL': { name: 'SQL', color: '#e38c00', icon: 'fas fa-database' },
      'Dockerfile': { name: 'Docker', color: '#384d54', icon: 'fab fa-docker' },
      'SCSS': { name: 'SCSS', color: '#cf649a', icon: 'fab fa-sass' },
      'Sass': { name: 'Sass', color: '#cf649a', icon: 'fab fa-sass' },
      'Less': { name: 'Less', color: '#1d365d', icon: 'fas fa-code' },
      'JSON': { name: 'JSON', color: '#292929', icon: 'fas fa-code' },
      'XML': { name: 'XML', color: '#0060ac', icon: 'fas fa-code' },
      'Markdown': { name: 'Markdown', color: '#083fa1', icon: 'fab fa-markdown' }
    };
    
    // Find the Development skills section
    const developmentSection = document.querySelector('.skill-category .skill-category-title i.lnr-code')?.closest('.skill-category');
    
    if (!developmentSection) {
      console.warn('⚠️ Development skills section not found');
      return;
    }
    
    const skillBarsContainer = developmentSection.querySelector('.skill-bars');
    if (!skillBarsContainer) {
      console.warn('⚠️ Skill bars container not found');
      return;
    }
    
    // Remove existing GitHub skills if any
    const existingGitHubSkills = skillBarsContainer.querySelectorAll('.github-skill, .github-skills-separator');
    existingGitHubSkills.forEach(element => element.remove());
    
    // Add separator for GitHub skills
    const separator = document.createElement('div');
    separator.className = 'github-skills-separator';
    separator.style.cssText = 'margin: 20px 0 15px 0; padding: 10px 0; border-top: 2px solid #f0f0f0;';
    separator.innerHTML = `
      <small class="text-muted" style="font-size: 11px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">
        <i class="fab fa-github" style="margin-right: 6px; color: #333;"></i> 
        Live GitHub Languages
      </small>
    `;
    
    skillBarsContainer.appendChild(separator);
    
    // Add GitHub language skills
    languagePercentages.forEach(({ language, percentage }) => {
      const langInfo = languageMap[language] || { 
        name: language, 
        color: '#333333', 
        icon: 'fas fa-code' 
      };
      
      // Ensure minimum visibility and realistic percentages
      const displayPercentage = Math.max(Math.min(percentage * 2.5, 95), 10);
      
      const skillItem = document.createElement('div');
      skillItem.className = 'skill-item github-skill';
      skillItem.setAttribute('data-language', language);
      
      skillItem.innerHTML = `
        <div class="skill-info">
          <span style="color: ${langInfo.color}; font-weight: 500;">
            <i class="${langInfo.icon}" style="margin-right: 6px; font-size: 14px;"></i>
            ${langInfo.name}
          </span>
          <span class="skill-percentage">${displayPercentage}%</span>
        </div>
        <div class="skill-bar">
          <div class="skill-progress github-progress" 
               data-skill="${displayPercentage}" 
               style="background: linear-gradient(90deg, ${langInfo.color}22, ${langInfo.color}88); width: 0%;">
          </div>
        </div>
      `;
      
      skillBarsContainer.appendChild(skillItem);
    });
    
    // Animate the new skill bars with staggered effect
    setTimeout(() => {
      const gitHubProgresses = skillBarsContainer.querySelectorAll('.github-progress');
      gitHubProgresses.forEach((progress, index) => {
        setTimeout(() => {
          const skillLevel = progress.getAttribute('data-skill');
          progress.style.width = skillLevel + '%';
          progress.style.transition = 'width 1.8s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
        }, index * 200); // Staggered animation
      });
    }, 300);
    
    console.log('✅ Development skills updated with GitHub languages:', languagePercentages.map(l => `${l.language} (${l.percentage}%)`));
    
  } catch (error) {
    console.error('❌ Error updating development skills:', error);
  }
}

// Initialize dynamic portfolio
// Initialize dynamic portfolio loading
window.initializeDynamicPortfolio = async function initializeDynamicPortfolio() {
  console.log('Initializing dynamic portfolio with GitHub integration...');
  
  // Wait for DOM to be ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', async () => {
      await insertGitHubProjects();
      await updateDevelopmentSkills();
    });
  } else {
    await insertGitHubProjects();
    await updateDevelopmentSkills();
  }
}

// Export for manual initialization if needed
export { insertGitHubProjects, updateDevelopmentSkills };