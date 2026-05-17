(function () {
  const form = document.getElementById('onboardingForm');
  const steps = Array.from(document.querySelectorAll('.onboarding-step'));
  const dots = Array.from(document.querySelectorAll('.onboarding-step-dot'));
  const progressFill = document.getElementById('progressFill');
  const progressLabel = document.getElementById('progressLabel');
  const backButton = document.getElementById('backButton');
  const nextButton = document.getElementById('nextButton');
  const businessNameInput = document.getElementById('businessNameInput');
  const subdomainInput = document.getElementById('subdomainInput');
  const domainPreview = document.getElementById('domainPreview');

  if (!form || !steps.length || !progressFill || !progressLabel || !backButton || !nextButton) {
    return;
  }

  let currentStep = 0;

  function slugify(value) {
    return String(value || '')
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 28);
  }

  function readValues() {
    const selectedIntegrations = Array.from(form.querySelectorAll('input[name="integrations"]:checked'))
      .map((input) => input.value);

    return {
      phone_number: String(form.elements.phone_number?.value || '').trim(),
      business_name: String(form.elements.business_name?.value || '').trim(),
      business_type: String(form.elements.business_type?.value || '').trim(),
      subdomain: String(form.elements.subdomain?.value || '').trim(),
      integrations: selectedIntegrations,
    };
  }

  function validateStep(stepIndex) {
    const values = readValues();

    if (stepIndex === 0) {
      return Boolean(values.phone_number && values.business_name && values.business_type);
    }
    if (stepIndex === 1) {
      return Boolean(values.subdomain);
    }
    return true;
  }

  function updatePreview() {
    const fromBusinessName = slugify(businessNameInput?.value || '');
    if (subdomainInput && !subdomainInput.dataset.userEdited && fromBusinessName) {
      subdomainInput.value = fromBusinessName;
    }
    const chosen = slugify(subdomainInput?.value || fromBusinessName || 'yourbrand');
    if (subdomainInput) {
      subdomainInput.value = chosen;
    }
    if (domainPreview) {
      domainPreview.textContent = `${chosen || 'yourbrand'}.yobobot.in`;
    }
  }

  function render() {
    steps.forEach((step, index) => {
      step.classList.toggle('active', index === currentStep);
    });

    dots.forEach((dot, index) => {
      dot.classList.toggle('active', index === currentStep);
    });

    const progress = ((currentStep + 1) / steps.length) * 100;
    progressFill.style.width = `${progress}%`;
    progressLabel.textContent = `Step ${currentStep + 1} of ${steps.length}`;
    backButton.style.visibility = currentStep === 0 ? 'hidden' : 'visible';
    nextButton.textContent = currentStep === steps.length - 1 ? 'Go To Dashboard' : 'Continue';
    updatePreview();
  }

  function saveAndGoToDashboard() {
    if (subdomainInput) {
      subdomainInput.value = slugify(subdomainInput.value || businessNameInput?.value || 'yourbrand');
    }
    form.requestSubmit();
  }

  dots.forEach((dot, index) => {
    dot.addEventListener('click', function () {
      if (index <= currentStep || validateStep(currentStep)) {
        currentStep = index;
        render();
      }
    });
  });

  if (businessNameInput) {
    businessNameInput.addEventListener('input', updatePreview);
  }

  if (subdomainInput) {
    subdomainInput.addEventListener('input', function () {
      subdomainInput.dataset.userEdited = '1';
      updatePreview();
    });
  }

  backButton.addEventListener('click', function () {
    currentStep = Math.max(0, currentStep - 1);
    render();
  });

  nextButton.addEventListener('click', function () {
    if (!validateStep(currentStep)) {
      window.alert('Please complete the required fields before continuing.');
      return;
    }

    if (currentStep === steps.length - 1) {
      saveAndGoToDashboard();
      return;
    }

    currentStep += 1;
    render();
  });

  render();
})();
