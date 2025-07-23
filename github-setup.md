# GitHub Setup Instructions

Follow these steps to push your Energy Monitor project to GitHub:

## 1. Create a new GitHub repository

1. Go to [GitHub](https://github.com/) and sign in to your account
2. Click on the "+" icon in the top right corner and select "New repository"
3. Enter "energy-monitor" as the repository name
4. Add a description (optional): "A comprehensive energy monitoring system built with Laravel and Filament PHP"
5. Choose whether to make the repository public or private
6. Do NOT initialize the repository with a README, .gitignore, or license (since we already have these files)
7. Click "Create repository"

## 2. Push your local repository to GitHub

After creating the repository, GitHub will show you commands to push an existing repository. Run the following commands in your terminal:

```bash
# Add the remote repository URL (replace 'yourusername' with your GitHub username)
git remote add origin https://github.com/yourusername/energy-monitor.git

# Push your code to GitHub
git push -u origin master
```

## 3. Verify your repository

1. Refresh your GitHub repository page
2. You should see all your files and commits in the GitHub repository

## 4. Set up GitHub Actions (Optional)

You can set up GitHub Actions for continuous integration and deployment:

1. In your GitHub repository, click on the "Actions" tab
2. GitHub will suggest workflows based on your project
3. Choose a Laravel workflow template
4. Customize the workflow file as needed
5. Commit the workflow file to your repository

## 5. Protect your main branch (Optional)

1. Go to your repository settings
2. Click on "Branches" in the left sidebar
3. Under "Branch protection rules", click "Add rule"
4. Enter "master" as the branch name pattern
5. Select protection options like requiring pull request reviews
6. Click "Create" to save the rule