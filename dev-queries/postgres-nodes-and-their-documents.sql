-- create materialized view if not exists sandstorm_kissearch_nodes_and_their_documents as
select *
from neos_contentrepository_domain_model_nodedata n;


create or replace function sandstorm_kissearch_is_document(
  nodetype_name text
) returns boolean
as
$$
begin
  return sandstorm_kissearch_is_document.nodetype_name in
         ('Neos.Demo:Document.Page', 'Neos.Demo:Document.LandingPage', 'Neos.Demo:Document.Homepage',
          'Neos.Demo:Document.BlogPosting', 'Neos.Demo:Document.Blog', 'Neos.Demo:Document.NotFoundPage',
          'SitePackage.Example:ExampleWithResourceUri', 'SitePackage.Example:ExampleDocument');
end;
$$ language 'plpgsql' immutable
                      parallel safe;

create or replace function sandstorm_kissearch_is_content(
  nodetype_name text
) returns boolean
as
$$
begin
  return sandstorm_kissearch_is_content.nodetype_name in
         ('Neos.NodeTypes.Navigation:Navigation', 'Neos.NodeTypes.AssetList:AssetList',
          'Neos.NodeTypes.ContentReferences:ContentReferences', 'Neos.NodeTypes.Html:Html',
          'Neos.Demo:Content.Registration', 'Neos.Demo:Content.ContactForm', 'Neos.Demo:Content.Headline',
          'Neos.Demo:Content.YouTube', 'Neos.Demo:Content.Carousel', 'Neos.Demo:Content.CarouselYouTube',
          'Neos.Demo:Content.Image', 'Neos.Demo:Content.BlogPostingList', 'Neos.Demo:Content.TextWithImage',
          'Neos.Demo:Content.Text', 'Neos.Demo:Content.Columns.Three', 'Neos.Demo:Content.Columns.Two',
          'Neos.Demo:Content.Columns.Four');
end;
$$ language 'plpgsql' immutable
                      parallel safe;

create materialized view if not exists sandstorm_kissearch_nodes_and_their_documents as
with recursive nodes_and_their_documents as
                 (select n.persistence_object_identifier,
                         n.identifier,
                         n.pathhash,
                         n.parentpathhash,
                         n.nodetype,
                         null::varchar(32)        as dimensionshash,
                         null::jsonb              as dimensionvalues,
                         null::text               as site_nodename,
                         n.identifier             as document_id,
                         n.properties ->> 'title' as document_title,
                         n.nodetype               as document_nodetype,
                         not n.hidden             as not_hidden,
                         not n.removed            as not_removed,
                         case
                           when n.hiddenbeforedatetime is not null or n.hiddenafterdatetime is not null then
                             jsonb_build_array(
                               jsonb_build_object(
                                 'before', n.hiddenbeforedatetime,
                                 'after', n.hiddenafterdatetime
                               )
                             )
                           else
                             '[]'::jsonb
                           end                    as timed_hidden
                  from neos_contentrepository_domain_model_nodedata n
                  where n.path = '/sites'
                    and not n.hidden
                    and not n.removed
                    and n.workspace = 'live'
                  union
                  select n.persistence_object_identifier,
                         n.identifier,
                         n.pathhash,
                         n.parentpathhash,
                         n.nodetype,
                         n.dimensionshash,
                         n.dimensionvalues,
                         -- site node name
                         coalesce(substring(n.path from '^/sites/(\w+)$'), r.site_nodename) as site_nodename,
                         -- document id
                         case
                           when sandstorm_kissearch_is_document(n.nodetype) then n.identifier
                           else r.document_id end                                           as document_id,
                         case
                           when sandstorm_kissearch_is_document(n.nodetype) then n.properties ->> 'title'
                           else r.document_title end                                        as document_title,
                         case
                           when sandstorm_kissearch_is_document(n.nodetype) then n.nodetype
                           else r.document_nodetype end                                     as document_nodetype,
                         not n.hidden and r.not_hidden                                      as not_hidden,
                         not n.removed and r.not_removed                                    as not_removed,
                         case
                           when n.hiddenbeforedatetime is not null or n.hiddenafterdatetime is not null then
                             r.timed_hidden || jsonb_build_object(
                               'before', n.hiddenbeforedatetime,
                               'after', n.hiddenafterdatetime
                                               )
                           else r.timed_hidden end                                          as timed_hidden
                  from neos_contentrepository_domain_model_nodedata n,
                       nodes_and_their_documents r
                  where r.pathhash = n.parentpathhash
                    and workspace = 'live'
                    and (r.dimensionshash is null or n.dimensionshash = r.dimensionshash))
select nd.persistence_object_identifier as persistence_object_identifier,
       nd.identifier                    as identifier,
       nd.document_id                   as document_id,
       nd.document_title                as document_title,
       nd.document_nodetype             as document_nodetype,
       nd.nodetype                      as nodetype,
       nd.dimensionshash                as dimensionshash,
       nd.dimensionvalues               as dimensionvalues,
       nd.site_nodename                 as site_nodename,
       case
         when jsonb_array_length(nd.timed_hidden) > 0 then
           nd.timed_hidden
         end                            as timed_hidden
from nodes_and_their_documents nd
where nd.site_nodename is not null
  and nd.not_hidden
  and nd.not_removed
  and (
  sandstorm_kissearch_is_document(nd.nodetype)
    or sandstorm_kissearch_is_content(nd.nodetype)
  );


select substring('/sites/foo' from '^/sites/(\w+)$');
select coalesce(substring('/sites/foo/bar' from '^/sites/(\w+)$'), 'foo');

select jsonb_build_array('1', '2');
