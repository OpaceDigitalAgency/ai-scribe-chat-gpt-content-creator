// Font Awesome Icons - Minimal Implementation
// This is a simplified version for the prototype

window.FontAwesome = {
  icons: {
    'check-circle': '\uf058',
    'exclamation-triangle': '\uf071',
    'times-circle': '\uf057',
    'star': '\uf005',
    'info-circle': '\uf05a',
    'lightbulb': '\uf0eb',
    'cog': '\uf013',
    'chart-bar': '\uf080'
  },

  // Helper function to create icon elements
  createIcon: function(iconName, className = '') {
    const icon = document.createElement('i');
    icon.className = `fa fas fa-${iconName} ${className}`;
    return icon;
  },

  // Initialize icons in the document
  init: function() {
    console.log('Font Awesome icons initialized');
  }
};

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  window.FontAwesome.init();
});
