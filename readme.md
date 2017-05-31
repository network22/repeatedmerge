# Repeatedmerge Tool Overview

Tool allows easy merging the changes across revision tree.
It's helpful to merge changes from trunk to release, 
from trunk to dev branches, etc... 
(see [Common Branching Patterns](http://svnbook.red-bean.com/en/1.7/svn.branchmerge.commonpatterns.html))

This picture shows the schema of trunk/release life

![Development workflow](http://i.stack.imgur.com/UmqdR.jpg)

Line "Dev" means trunk line.

This utility help to merge current trunk version to release
(arrows to release 1, release 2 etc)

# Requirements

* PHP 5.2+
* SVN should be installed and accessible by PATH
* Optional, Windows: Tortoise SVN should be installed and accessible by PATH (for interactive option)
* The destination branch should be checkout-ed to some local directory
* The custom SVN property bswsvn:masterbranch should be set for destination branch (see bellow)

# General description

Each branch that used for repeat merge has the property **bswsvn:masterbranch**.
This property has to point to path to master branch. Master branch means 
the branch from where the changes should be merged to this branch. 
Path should be "repository internal", i.e. without name of repository and host.
In other words the path **should be the same as** used in 
the SVN **svn:mergeinfo** property.

For example for release branch the **bswsvn:masterbranch** should be
```
/release
```

It means that utility will merge the changes from /trunk to 
this (`/release`) branch.

Utility uses SVN **svn:mergeinfo** property to check the last merge release 
number from parent branch and run svn merge for next release number till 
head from parent branch.

**Important note:** It's necessary for SVN **svn:mergeinfo** property for 
this branch contains the last merge release numbers for master branch 
because utility uses it. So before first start to use the utility for this 
branch you should to do one of the following thing

* makes one merge manually: SVN adds the master branch to **svn:mergeinfo** property with merged releases
* **or** manually add the master branch to **svn:mergeinfo** property with starting release number. Because usually you create the branch by copy from master branch, just add the line
```
...
/master/branch/path:XXXX-XXXX
```
where XXXX is the made by copy release number. In this case the the utility will know from which release it should to start merge in first time call. 


# Using utility

Run format:
```
php repeatedmerge.php target_dir [--real] [-i]
```
Parameters:

* target_dir (mandatory): path of local directory where the destination branch checkout-ed.
* --real: flag for real merge or simulate. If present - utility does merge in real, if omitted - simulate.
* -i: (Windows only) Use TortoiseSVN for commit instead of SVN utility

Run utility with necessary parameters. Utility does merge (simulate merge) 
from source branch for revisions from last merge till SVN head and display
the proposed comment for commit to console. 

If conflict exists utility also display message about conflicts.

In case of conflict you should 
* manually resolve the conflicts of merge (if exists) 
* manually commit the changes using proposed by utility the comment.

Use the proposed by utility comment for commit the merges for well organize 
the SVN comments.

# Logging

Utility writes the log in the run directory.