# Statext: PHP Static site generator

## To build

```
vendor/psecio/statext/bin/statext build --source=source/ --target=output/
```

This library makes use of Twig for templating

## Directory structure

### For source

An example where the `index` page is the only content

```
source/
- content /
-- index.md
- templates/
-- modules/
--- sidebar.twig
-- default.twig
-- layout.twig
```

*Contents of `default.twig`:*

```
{% extends 'layout.twig' %}

{% block content %}
{{ markup|raw }}
{% endblock %}
```

*Contents of `layout.twig`:*
```
<html>
    <body>
        <table cellpadding="0" cellspacing="0">
        <tr>
            <td style="width:200px;vertical-align:top">
                <h3>Sidebar</h3>
                {% include 'modules/sidebar.twig' %}
            </td>
            <td>
                {% block content %}{% endblock %}
            </td>
        </tr>
        </table>
    </body>
</html>
```

*Contents of `sidebar.twig`:*
```
<h3>Links</h3>

{% for page in pages %}
    <a href="{{ page.display.path }}">{{ page.meta.title }}</a><br/>
{% endfor %}

```
